<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

define('BLOCK_FILE', true);
define('FUNC_FILE', true);

# Configuration directory
define('CONFIG_DIR', BASE_DIR.'/config');

# Storage directories for internal data
define('BACKUP_DIR', BASE_DIR.'/storage/backup');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('COUNTER_DIR', BASE_DIR.'/storage/counter');
define('LOGS_DIR', BASE_DIR.'/storage/logs');
define('SITEMAP_DIR', BASE_DIR.'/storage/sitemap');

# Uploads directory for user content
define('UPLOADS_DIR', BASE_DIR.'/uploads');

# Load all /config/*.php into a unified $conf array; apply local.php overrides
function getConfig(): array {
    $conf = [];
    $default_files = [];
    $files = glob(CONFIG_DIR.'/*.php');
    if ($files === false) $files = [];
    sort($files);
    $skip = ['local.php', 'system.php', 'header.php', 'chmod.php'];
    foreach ($files as $file) {
        if (in_array(basename($file), $skip)) continue;
        $data = require $file;
        if (is_array($data)) {
            $conf = array_merge($conf, $data);
            $default_files[] = $file;
        }
    }
    $conf['dev_mode'] ??= false;
    $local_file = CONFIG_DIR.'/local.php';
    $local = [];
    if (file_exists($local_file)) {
        $data = include $local_file;
        if (is_array($data)) $local = $data;
    }
    $stored_finger = $local['_meta']['base_fingerprint'] ?? '';
    unset($local['_meta']);
    if ($local !== []) $conf = filterConfigMerge($conf, $local);
    $finger = getConfigFingerprint($default_files);
    if ($conf['dev_mode'] && $finger !== $stored_finger) {
        setConfigFingerprint($local_file, $finger);
    }
    return $conf;
}

# Safe recursive merge: override only existing keys with matching type; ignore unknown keys
function filterConfigMerge(array $base, array $override): array {
    foreach ($override as $key => $value) {
        if (!array_key_exists($key, $base)) continue;
        if (is_array($base[$key]) && is_array($value)) {
            $base[$key] = filterConfigMerge($base[$key], $value);
        } elseif (gettype($base[$key]) === gettype($value)) {
            $base[$key] = $value;
        }
    }
    return $base;
}

# Compute sha1 fingerprint over config files; includes filename to detect additions/removals
function getConfigFingerprint(array $files): string {
    $hash = '';
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        $file_hash = sha1_file($file);
        if ($file_hash !== false) $hash .= basename($file).$file_hash;
    }
    return sha1($hash);
}

# Read local.php as array, update only _meta.base_fingerprint, write atomically
function setConfigFingerprint(string $local_file, string $fingerprint): void {
    $data = [];
    if (file_exists($local_file)) {
        $existing = include $local_file;
        if (is_array($existing)) $data = $existing;
    }
    $data['_meta']['base_fingerprint'] = $fingerprint;
    $exported = var_export($data, true);
    $exported = preg_replace('/array \(/', '[', $exported);
    $exported = preg_replace('/^(\s*)\)(,?)$/m', '$1]$2', $exported);
    $content = "<?php\nreturn ".$exported.";\n";
    $tmp = $local_file.'.tmp';
    $is_new = !file_exists($local_file);
    if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
        if (!rename($tmp, $local_file)) {
            unlink($tmp);
        } elseif ($is_new) {
            chmod($local_file, 0640);
        }
    }
}

# System file include
require_once BASE_DIR.'/core/security.php';
require_once BASE_DIR.'/core/legacy.php';

if (defined('MODULE_FILE')) {
    require_once BASE_DIR.'/core/user.php';
} elseif (defined('ADMIN_FILE')) {
    require_once BASE_DIR.'/core/admin.php';
}

$theme = getTheme();
if (is_file(BASE_DIR.'/templates/'.$theme.'/index.php')) require_once BASE_DIR.'/templates/'.$theme.'/index.php';
require_once BASE_DIR.'/core/template.php';

# Format block
function getBlocks(string $side, string $fly = ''): void {
    global $db, $conf, $locale, $name, $home, $pos, $b_id, $blockfile;
    static $barr;
    if ($conf['multilingual'] == 1) {
        $querylang = "AND (blanguage = :loc OR blanguage = '')";
        $qlang_params = ['loc' => $locale];
    } else {
        $querylang = "";
        $qlang_params = [];
    }
    $pos = strtolower($side[0]);
    $side = $pos;
    if (!isset($barr)) {
        $result = $db->getSqlQuery("SELECT bid, bkey, title, content, url, blockfile, view, expire, action, bposition, which FROM ".PREFIX_DB."_blocks WHERE active = '1' ".$querylang." ORDER BY weight ASC", $qlang_params);
        while(list($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $which) = $db->getSqlRow($result)) {
            $bid = intval($bid);
            $content = bb_decode($content, "all");
            $view = intval($view);
            $where_mas = explode(",", $which);
            $barr[] = [$bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $where_mas];
        }
    }
    if ($fly != "") {
        $b_id = 0;
        $flag = 0;
        $blockfile = "";
        if (false === strpos($fly, "-")) {
            $b_id = intval($fly);
        } else {
            $blockfile = trim($fly);
        }
        $ci = count($barr);
        for ($i = 0; $i < $ci; $i++) {
            if (($b_id != 0 && $barr[$i][0] == $b_id) || ($blockfile != "" && $barr[$i][5] == $blockfile)) {
                list($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $where_mas) = $barr[$i];
                $b_id = $bid;
                $flag = 1;
                break;
            }
        }
        if ($flag == 1) {
            if (in_array("flyfix", $where_mas)) {
                switch ($where_mas[0]) {
                    case "all":
                    $flag_where = 1;
                    break;
                    case "":
                    $flag_where = 1;
                    break;
                    case "infly":
                    $flag_where = 0;
                    break;
                    case "home":
                    $flag_where = ($home == 1) ? 1 : 0;
                    break;
                    case "ihome":
                    if ($home == 1) $flag_where = 1;
                    default:
                    if (empty($home)) {
                        foreach ($where_mas as $val) {
                            if ($val == $name) $flag_where = 1;
                        }
                    }
                    break;
                }
                if (in_array("otricanie", $where_mas)) $flag_where = ($flag_where) ? 0 : 1;
            } else {
                $flag_where = 1;
            }
            if ($flag_where == 1) {
                if ($view == 0) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url); return;
                } elseif ($view == 1 && is_user() || is_moder()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url); return;
                } elseif ($view == 2 && is_moder()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url); return;
                } elseif ($view == 3 && !is_user() || is_moder()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url); return;
                }
            }
        }
    } else {
        $ci = count($barr);
        for ($i = 0; $i < $ci; $i++) {
            if ($barr[$i][9] != $side) continue;
            $flag_where = 0;
            $where_mas = $barr[$i][10];
            switch ($where_mas[0]) {
                case "all":
                $flag_where = 1;
                break;
                case "":
                $flag_where = 1;
                break;
                case "infly":
                $flag_where = 0;
                break;
                case "home":
                $flag_where = ($home == 1) ? 1 : 0;
                break;
                case "ihome":
                if ($home == 1) $flag_where = 1;
                default:
                if (empty($home)) {
                    foreach ($where_mas as $val) {
                        if ($val == $name) $flag_where = 1;
                    }
                }
                break;
            }
            if (in_array("otricanie", $where_mas)) $flag_where = ($flag_where) ? 0 : 1;
            if ($flag_where == 1) {
                list($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $where_mas) = $barr[$i];
                $b_id = $bid;
                if ($expire && $expire < time()) {
                    if ($action == "d") {
                        $db->getSqlQuery("UPDATE ".PREFIX_DB."_blocks SET active = '0', expire = '0' WHERE bid = :bid", ['bid' => $bid]);
                        return;
                    } elseif ($action == "r") {
                        $db->getSqlQuery("DELETE FROM ".PREFIX_DB."_blocks WHERE bid = :bid", ['bid' => $bid]);
                        return;
                    }
                }
                switch ($bkey) {
                    case "admin":
                    echo adminblock();
                    break;
                    case "userbox":
                    echo userblock();
                    break;
                    default:
                    if ($view == 0) {
                        render_blocks($side, $blockfile, $title, $content, $bid, $url);
                    } elseif ($view == 1 && is_user() || is_moder()) {
                        render_blocks($side, $blockfile, $title, $content, $bid, $url);
                    } elseif ($view == 2 && is_moder()) {
                        render_blocks($side, $blockfile, $title, $content, $bid, $url);
                    } elseif ($view == 3 && !is_user() || is_moder()) {
                        render_blocks($side, $blockfile, $title, $content, $bid, $url);
                    }
                    break;
                }
            }
        }
    }
}

# Convert Markdown+BB source to safe HTML
function filterMarkdown(string $src, string $mod = '', bool $safe = true): string {
    static $md = null;
    $md ??= new class {

        private array  $stash = [];
        private string $salt  = '';
        private array  $hids  = [];
        private string $mod   = 'all';

        public function filterHtml(string $src, bool $safe, string $mod): string {
            $this->stash = [];
            $this->hids  = [];
            $this->salt  = bin2hex(random_bytes(4));
            $this->mod   = $mod !== '' ? strtolower($mod) : 'all';
            $out = $this->filterMain($src, $safe);
            $sentinel = "\x02{$this->salt}:";
            while (str_contains($out, $sentinel)) {
                $prev = $out;
                $out  = strtr($out, $this->stash);
                if ($out === $prev) break;
            }
            return trim($out);
        }

        // Add a comma before each next VALUES row (except first row and after split markers)
        private function filterNest(string $src, bool $safe): string {
            return $this->filterMain($src, $safe);
        }

        private function filterMain(string $src, bool $safe): string {
            $src = str_replace(["\r\n", "\r"], "\n", $src);
            $src = $this->filterBbBlocks($src, $safe);
            $src = $this->filterFencedCode($src);
            if ($safe) $src = $this->filterIndentedCode($src);
            $src = $this->filterInlineCode($src);
            $src = $this->filterBlocks($src, $safe);
            return $src;
        }

        // Helpers

        private function addStash(string $html): string {
            $key = "\x02{$this->salt}:".count($this->stash)."\x03";
            $this->stash[$key] = $html;
            return $key;
        }

        private function filterEsc(string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        private function filterDec(string $s): string {
            return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        private function filterText(string $s): string {
            $pat   = '/(\x02'.preg_quote($this->salt, '/').':\d+\x03)/';
            $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
            return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
        }

        private function filterInline(string $txt, bool $safe): string {
            return $this->filterInlines($safe ? $this->filterText($txt) : $txt, $safe);
        }

        private function filterUrl(string $url): string {
            $url = trim($url);
            return preg_match('/^(?:https?:\/\/|mailto:|[\/\.#?])/i', $url) ? $url : '#';
        }

        // BB blocks (stash before Markdown parsing)

        private function filterBbBlocks(string $src, bool $safe): string {
            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace('/\[hr\]/si', $this->addStash('<hr>'), $src) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace('/\[li\]/si', $this->addStash('&bull; '), $src) ?? $src;

            // *01 smilies
            if (preg_match('/\*(\d{2})/', $src)) {
                $src = preg_replace_callback(
                    '/\*(\d{2})/',
                    function(array $m): string {
                        $num = $this->filterEsc($m[1]);
                        $img = img_find('smilies/'.$num.'.gif');
                        return $this->addStash('<img src="'.$this->filterEsc($img).'" alt="'._SMILIE.' - '.$num.'" title="'._SMILIE.' - '.$num.'">');
                    },
                    $src
                ) ?? $src;
            }

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace_callback(
                '/\[usehtml\](.*?)\[\/usehtml\]/si',
                function(array $m) use ($safe): string {
                    if ($safe) return $m[0];
                    $html = htmlspecialchars_decode(replace_break($m[1]), ENT_QUOTES);
                    return $this->addStash($html);
                },
                $src
            ) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace_callback(
                '/\[usephp\](.*?)\[\/usephp\]/si',
                function(array $m) use ($safe): string {
                    if ($safe) return $m[0];
                    $rep = str_replace(['&#036;', '&#092;'], ['$', '\\'], $m[1]);
                    ob_start();
                    try {
                        eval(htmlspecialchars_decode(replace_break($rep), ENT_QUOTES));
                        $out = ob_get_clean();
                    } catch (Throwable $ex) {
                        ob_end_clean();
                        $out = '';
                    }
                    return $this->addStash((string)$out);
                },
                $src
            ) ?? $src;

            // [tabs=n]...[tab=title]...[/tab]...[/tabs]
            $src = preg_replace_callback(
                '/\[tabs=(.*?)\](.*?)\[\/tabs\]/si',
                function(array $m) use ($safe): string {
                    $num = (int)trim($m[1]);
                    $rep = (string)$m[2];
                    $cnt = preg_match_all('/\[tab=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/tab\]/siu', $rep, $mm);
                    if (!$cnt) return $m[0];
                    $ttl = [];
                    $txt = [];
                    for ($i = 0; $i < $cnt; $i++) {
                        $ttl[] = $mm[1][$i];
                        $txt[] = $this->filterNest($mm[2][$i], $safe);
                    }
                    return $this->addStash((string)getNaviTabs($num, 'tab', $ttl, $txt));
                },
                $src
            ) ?? $src;

            // [code]...[/code]
            $src = preg_replace_callback(
                '/\[code\](.*?)\[\/code\]/si',
                function(array $m): string {
                    $txt  = str_replace('?', '&#063;', (string)$m[1]);
                    $html = setTemplateBasic('code', ['{%title%}' => _CODE, '{%content%}' => $this->filterEsc($txt)]);
                    return $this->addStash((string)$html);
                },
                $src
            ) ?? $src;

            // [code=lang]...[/code]
            $src = preg_replace_callback(
                '/\[code=(.*?)\](.*?)\[\/code\]/si',
                function(array $m): string {
                    return $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1], 2 => $m[2]]));
                },
                $src
            ) ?? $src;

            // [php]...[/php]
            $src = preg_replace_callback(
                '/\[php\](.*?)\[\/php\]/si',
                function(array $m): string {
                    return $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1]]));
                },
                $src
            ) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            while (preg_match('/\[quote\](.*?)\[\/quote\]/si', $src)) {
                $src = preg_replace_callback(
                    '/\[quote\](.*?)\[\/quote\]/si',
                    function(array $m) use ($safe): string {
                        $txt  = $this->filterNest($m[1], $safe);
                        $html = setTemplateBasic('quote', ['{%title%}' => _QUOTE, '{%text%}' => $txt]);
                        return $this->addStash((string)$html);
                    },
                    $src
                ) ?? $src;
            }

            // Add a comma before each next VALUES row (except first row and after split markers)
            while (preg_match('/\[hide\](.*?)\[\/hide\]/si', $src)) {
                $src = preg_replace_callback(
                    '/\[hide\](.*?)\[\/hide\]/si',
                    function(array $m) use ($safe): string {
                        $show = (defined('ADMIN_FILE') || is_user());
                        $txt  = $show ? $this->filterNest($m[1], $safe) : (string)_HIDETEXT;
                        $html = setTemplateBasic('hide', ['{%title%}' => _HIDE, '{%text%}' => $txt]);
                        return $this->addStash((string)$html);
                    },
                    $src
                ) ?? $src;
            }

            // [attach=...]
            if (stripos($src, '[attach=') !== false) {
                $src = $this->filterAttach($src);
            }

            return $src;
        }

        private function filterAttach(string $src): string {
            $mod = $this->mod !== '' ? $this->mod : 'all';
            $up  = include 'config/uploads.php';
            $cfg = is_array($up) ? ($up['uploads'] ?? []) : [];
            $ft  = include 'config/filetype.php';
            $tpl = is_array($ft) ? ($ft['filetype'] ?? []) : [];

            if (stripos($src, 'rel=') !== false && stripos($src, 'width=') !== false) {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+) rel=([a-zA-Z0-9_\-]+)\]/siu';
            } elseif (stripos($src, 'width=') !== false) {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+)\]/siu';
            } else {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+)\]/siu';
            }

            if (!preg_match_all($re, $src, $mm, PREG_SET_ORDER)) return $src;

            $con = explode('|', (string)($cfg[$mod] ?? ''));
            $twd = $con[6] ?? ($cfg['width'] ?? '250');
            $img = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];

            foreach ($mm as $m) {
                $fn   = (string)$m[1];
                $al   = (string)$m[2];
                $tl   = (string)$m[3];
                $wd   = $m[4] ?? '';
                $hg   = $m[5] ?? '';
                $rl   = $m[6] ?? '';
                $ext  = strtolower((string)substr((string)strrchr($fn, '.'), 1));
                $file = 'uploads/'.$mod.'/'.$fn;
                $timg = $file;

                if (in_array($ext, $img, true)) {
                    $tfile = 'uploads/'.$mod.'/thumb/'.$fn;
                    $tdir  = 'uploads/'.$mod.'/thumb';
                    if ($mod !== '' && file_exists($file) && !file_exists($tfile)) {
                        if (!file_exists($tdir)) mkdir($tdir);
                        $ok   = create_img_gd($file, $tfile, $twd);
                        $timg = $ok ? $tfile : $file;
                    } else {
                        $timg = $tfile;
                    }
                    if (file_exists($file)) [$wd, $hg] = getimagesize($file);
                }

                $tmp = $tpl[$ext] ?? '<a href="[src]" target="_blank" title="[title]">[title]</a>';
                $tmp = str_replace('[src]',    $file, $tmp);
                $tmp = str_replace('[tsrc]',   (string)$timg, $tmp);
                $tmp = (!empty($wd) && (int)$wd)
                     ? str_replace('[width]',  (string)$wd, $tmp)
                     : str_replace('[width]',  (string)($cfg['width'] ?? '500'), $tmp);
                $tmp = str_replace('[twidth]', (string)$twd, $tmp);
                $tmp = (!empty($hg) && (int)$hg)
                     ? str_replace('[height]', (string)$hg, $tmp)
                     : str_replace('[height]', (string)($cfg['height'] ?? '500'), $tmp);
                $tmp = str_replace('[align]',  $al, $tmp);
                $tmp = str_replace('[title]',  $tl, $tmp);
                $tmp = str_replace('[quot]',   '&quot;', $tmp);
                $tmp = str_replace('[rel]',    $rl !== '' ? $rl : 'alternate', $tmp);

                $src = str_replace($m[0], $this->addStash($tmp), $src);
            }

            return $src;
        }

        // Code protection

        private function filterFencedCode(string $src): string {
            return preg_replace_callback(
                '/(^(`{3,}|~{3,})[ \t]*([\w\-]*)[^\n]*\n(.*?)\n^\2[ \t]*$)/ms',
                function($m) {
                    $cls = $m[3] ? ' class="language-'.$this->filterEsc($m[3]).'"' : '';
                    return $this->addStash('<pre><code'.$cls.'>'.$this->filterEsc($m[4]).'</code></pre>');
                },
                $src
            ) ?? $src;
        }

        private function filterIndentedCode(string $src): string {
            return preg_replace_callback(
                '/(?:^(?:    |\t).+\n?)+/m',
                fn($m) => $this->addStash(
                    '<pre><code>'.$this->filterEsc(preg_replace('/^(?:    |\t)/m', '', rtrim($m[0]))).'</code></pre>'
                )."\n",
                $src
            ) ?? $src;
        }

        private function filterInlineCode(string $src): string {
            return preg_replace_callback(
                '/``(.+?)``|`([^`\n]+)`/s',
                function($m) {
                    $txt = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                    return $this->addStash('<code>'.$this->filterEsc($txt).'</code>');
                },
                $src
            ) ?? $src;
        }

        // Blocks

        private function filterBlocks(string $src, bool $safe): string {
            $lines = explode("\n", $src);
            $n     = count($lines);
            $pat   = '/^\x02'.preg_quote($this->salt, '/').':\d+\x03$/';
            $out   = '';
            $i     = 0;

            while ($i < $n) {
                $line = $lines[$i];
                $trim = ltrim($line);

                if (preg_match($pat, trim($line))) { $out .= $line."\n"; $i++; continue; }
                if ($trim === '') { $out .= "\n"; $i++; continue; }

                if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?$/', $trim, $m)) {
                    $lvl = strlen($m[1]);
                    $id  = $this->getHeadingId($m[2], $lvl);
                    $out .= '<h'.$lvl.' id="'.$id.'">'.$this->filterInline($m[2], $safe).'</h'.$lvl.'>'."\n";
                    $i++; continue;
                }

                if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $trim)) {
                    $out .= "<hr>\n"; $i++; continue;
                }

                if (str_starts_with($trim, '>')) {
                    [$bq, $i] = $this->getBlockquote($lines, $i, $n);
                    $out .= "<blockquote>\n".$this->filterBlocks(implode("\n", $bq), $safe)."</blockquote>\n";
                    continue;
                }

                if (preg_match('/^([ \t]*)([*+\-]|\d+\.)\s+/', $line, $m)) {
                    [$html, $i] = $this->filterList($lines, $i, strlen($m[1]), $safe);
                    $out .= $html; continue;
                }

                if (isset($lines[$i + 1]) && str_contains($trim, '|')
                    && preg_match('/^\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)+\|?$/', $lines[$i + 1])
                ) {
                    [$html, $i] = $this->filterTable($lines, $i, $safe);
                    $out .= $html; continue;
                }

                if (isset($lines[$i + 1]) && $trim !== '') {
                    if (preg_match('/^=+\s*$/', $lines[$i + 1])) {
                        $id = $this->getHeadingId($trim, 1);
                        $out .= '<h1 id="'.$id.'">'.$this->filterInline($trim, $safe)."</h1>\n";
                        $i += 2; continue;
                    }
                    if (preg_match('/^-+\s*$/', $lines[$i + 1]) && !preg_match('/^[*+\-]\s/', $trim)) {
                        $id = $this->getHeadingId($trim, 2);
                        $out .= '<h2 id="'.$id.'">'.$this->filterInline($trim, $safe)."</h2>\n";
                        $i += 2; continue;
                    }
                }

                if (!$safe && preg_match('/^<\/?[a-zA-Z]/', $trim)) {
                    $raw = '';
                    while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++]."\n"; }
                    $raw = strtr($raw, $this->stash);
                    $out .= $this->addStash(str_replace(['&#034;', '&#039;'], ['"', "'"], $raw));
                    continue;
                }

                $para = [];
                while ($i < $n && trim($lines[$i]) !== ''
                    && !preg_match('/^#{1,6}\s|^(?:\*{3,}|-{3,}|_{3,})\s*$/', ltrim($lines[$i]))
                ) {
                    $para[] = $lines[$i++];
                }
                $out .= '<p>'.$this->filterInline(implode("\n", $para), $safe)."</p>\n";
            }

            return $out;
        }

        private function getBlockquote(array $lines, int $i, int $n): array {
            $bq = [];
            while ($i < $n) {
                $t = ltrim($lines[$i]);
                if (str_starts_with($t, '>')) {
                    $bq[] = preg_replace('/^[ \t]*>[ \t]?/', '', $lines[$i++]);
                } elseif (trim($lines[$i]) === '') {
                    $j = $i + 1;
                    while ($j < $n && trim($lines[$j]) === '') $j++;
                    if ($j < $n && str_starts_with(ltrim($lines[$j]), '>')) { $bq[] = ''; $i++; }
                    else break;
                } else break;
            }
            return [$bq, $i];
        }

        private function getHeadingId(string $raw, int $lvl): string {
            $txt  = preg_replace('/\x02'.preg_quote($this->salt, '/').':\d+\x03/', '', $raw);
            $id   = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strip_tags($txt)), '-'));
            if ($id === '') $id = 'h'.$lvl;
            $base = $id;
            if (isset($this->hids[$base])) $id = $base.'-'.(++$this->hids[$base]);
            else $this->hids[$base] = 0;
            return $id;
        }

        private function filterList(array $lines, int $i, int $ind, bool $safe): array {
            $n   = count($lines);
            $ord = (bool)preg_match('/^\s*\d+\./', $lines[$i]);
            $tag = $ord ? 'ol' : 'ul';
            $it  = [];
            $cur = null;

            while ($i < $n) {
                $line = $lines[$i];
                if (trim($line) === '') { if ($cur !== null) $cur .= "\n"; $i++; continue; }
                $sp = strlen($line) - strlen(ltrim($line));
                if ($sp === $ind && preg_match('/^[ \t]*(?:[*+\-]|\d+\.)\s+(.*)$/', $line, $m)) {
                    if ($cur !== null) $it[] = $cur;
                    $cur = $m[1]; $i++;
                } elseif ($sp > $ind) {
                    $cur .= "\n".$line; $i++;
                } else break;
            }
            if ($cur !== null) $it[] = $cur;

            $html = '<'.$tag.">\n";
            foreach ($it as $item) {
                $item = trim($item);
                if (preg_match('/^\[(x| )\]\s+(.*)/si', $item, $tm)) {
                    $chk = $tm[1] === 'x' ? ' checked' : '';
                    $lbl = trim($tm[2]);
                    $lbl = str_contains($lbl, "\n") ? $this->filterBlocks($lbl, $safe) : $this->filterInline($lbl, $safe);
                    $html .= '<li><input type="checkbox" disabled'.$chk.'> '.$lbl."</li>\n";
                } elseif (str_contains($item, "\n")) {
                    $html .= '<li>'.$this->filterBlocks($item, $safe)."</li>\n";
                } else {
                    $html .= '<li>'.$this->filterInline($item, $safe)."</li>\n";
                }
            }
            return [$html.'</'.$tag.">\n", $i];
        }

        private function filterTable(array $lines, int $i, bool $safe): array {
            $heads = array_map('trim', explode('|', trim($lines[$i],   " |\t")));
            $seps  = array_map('trim', explode('|', trim($lines[$i+1], " |\t")));
            $cols  = max(count($heads), count($seps));
            $al    = array_map(fn($a) =>
                preg_match('/^:-+:$/', $a) ? ' style="text-align:center"' :
               (preg_match('/^-+:$/', $a)  ? ' style="text-align:right"'  :
               (preg_match('/^:-+$/', $a)  ? ' style="text-align:left"'   : '')),
                $seps
            );
            $i += 2;
            $html = "<table>\n<thead>\n<tr>";
            foreach (array_pad($heads, $cols, '') as $j => $h) {
                $html .= '<th'.($al[$j] ?? '').'>'.$this->filterInline($h, $safe).'</th>';
            }
            $html .= "</tr>\n</thead>\n<tbody>\n";
            while (isset($lines[$i]) && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                $cells = array_map('trim', explode('|', trim($lines[$i], " |\t")));
                $html .= '<tr>';
                foreach (array_pad($cells, $cols, '') as $j => $c) {
                    $html .= '<td'.($al[$j] ?? '').'>'.$this->filterInline($c, $safe).'</td>';
                }
                $html .= "</tr>\n"; $i++;
            }
            return [$html."</tbody>\n</table>\n", $i];
        }

        // Inlines: Markdown + BB

        private function filterInlines(string $src, bool $safe): string {

            // BB inline

            // ed2k links - must come BEFORE generic [url] patterns
            $src = preg_replace_callback(
                '/\[url\](ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\[\/url\]/si',
                function(array $m): string {
                    $url = $this->filterEsc($this->filterDec($m[1]));
                    $ttl = $this->filterEsc($this->filterDec($m[2]));
                    return $this->addStash('eMule/eDonkey: <a href="'.$url.'" target="_blank" title="'.$ttl.'">'.$ttl.'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[url=(ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\](.*?)\[\/url\]/si',
                function(array $m): string {
                    $url = $this->filterEsc($this->filterDec($m[1]));
                    $ttl = $this->filterEsc($this->filterDec($m[2]));
                    return $this->addStash('<a href="'.$url.'" target="_blank" title="'.$ttl.'">'.(string)$m[4].'</a>');
                },
                $src
            ) ?? $src;

            for ($i = 0; $i < 3; $i++) {
                $src = preg_replace('/\[b\](.*?)\[\/b\]/si', '<strong>$1</strong>', $src) ?? $src;
                $src = preg_replace('/\[i\](.*?)\[\/i\]/si', '<em>$1</em>', $src) ?? $src;
                $src = preg_replace('/\[u\](.*?)\[\/u\]/si', '<u>$1</u>', $src) ?? $src;
                $src = preg_replace('/\[s\](.*?)\[\/s\]/si', '<del>$1</del>', $src) ?? $src;
            }

            $src = preg_replace_callback(
                '/\[color=([^\]]+)\](.*?)\[\/color\]/si',
                function(array $m): string {
                    $color = strtolower(trim($m[1]));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $color) && !preg_match('/^[a-z]+$/', $color)) return $m[2];
                    return '<span style="color:'.$this->filterEsc($color).'">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[family=([A-Za-z ]+)\](.*?)\[\/family\]/si',
                function(array $m): string {
                    return '<span style="font-family:'.$this->filterEsc(trim($m[1])).'">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[size=([0-9]{1,2})\](.*?)\[\/size\]/si',
                function(array $m): string {
                    $size = max(8, min(48, (int)$m[1]));
                    return '<span style="font-size:'.$size.'px">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[(left|right|center|justify)\](.*?)\[\/\1\]/si',
                function(array $m): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right', 'center', 'justify'], true)) return $m[2];
                    return '<div style="text-align:'.$align.';">'.$m[2].'</div>';
                },
                $src
            ) ?? $src;

            // [mail] / [mail=]
            $src = preg_replace_callback(
                '/\[mail\](.*?)\[\/mail\]/si',
                function(array $m): string {
                    $mail = trim($this->filterDec($m[1]));
                    if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[1];
                    $mail = $this->filterEsc($mail);
                    return $this->addStash('<a href="mailto:'.$mail.'">'.$mail.'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[mail\s*=\s*([^\]]+)\](.*?)\[\/mail\]/si',
                function(array $m): string {
                    $mail = trim($this->filterDec($m[1]));
                    if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[2];
                    $mail = $this->filterEsc($mail);
                    return $this->addStash('<a href="mailto:'.$mail.'">'.$m[2].'</a>');
                },
                $src
            ) ?? $src;

            // [url] / [url=]
            $src = preg_replace_callback(
                '/\[url\](.*?)\[\/url\]/si',
                function(array $m) use ($safe): string {
                    $url = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $href = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<a href="'.$href.'">'.$this->filterEsc($url).'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[url=([^\]]+)\](.*?)\[\/url\]/si',
                function(array $m) use ($safe): string {
                    $url = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $href = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<a href="'.$href.'">'.$m[2].'</a>');
                },
                $src
            ) ?? $src;

            // [img] / [img=align] / [img alt=] / [img=align alt=]
            $src = preg_replace_callback(
                '/\[img\](.*?)\[\/img\]/si',
                function(array $m) use ($safe): string {
                    $url  = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2 = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $alt  = $this->filterEsc($url);
                    return $this->addStash('<img src="'.$src2.'" alt="'.$alt.'" title="'.$alt.'" class="sl_img">');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img=([a-zA-Z]+)\](.*?)\[\/img\]/si',
                function(array $m) use ($safe): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                    $url   = trim($this->filterDec($m[2]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2  = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $alt   = $this->filterEsc($url);
                    return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$alt.'" title="'.$alt.'" class="sl_img">');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
                function(array $m) use ($safe): string {
                    $alt  = $this->filterEsc(trim($this->filterDec($m[1])));
                    $url  = trim($this->filterDec($m[2]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2 = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<img src="'.$src2.'" alt="'.$alt.'" title="'.$alt.'" class="sl_img">');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img=([a-zA-Z]+)\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
                function(array $m) use ($safe): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                    $alt   = $this->filterEsc(trim($this->filterDec($m[2])));
                    $url   = trim($this->filterDec($m[3]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2  = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$alt.'" title="'.$alt.'" class="sl_img">');
                },
                $src
            ) ?? $src;

            // Markdown inline

            $src = preg_replace_callback(
                '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+"([^"]*)")?\)/',
                function($m) use ($safe) {
                    $url = $this->filterEsc($safe ? $this->filterUrl($this->filterDec($m[2])) : $this->filterDec($m[2]));
                    $alt = $this->filterEsc($this->filterDec($m[1]));
                    $ttl = isset($m[3]) ? ' title="'.$this->filterEsc($this->filterDec($m[3])).'"' : '';
                    return $this->addStash('<img src="'.$url.'" alt="'.$alt.'"'.$ttl.'>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[([^\]]+)\]\(([^\s)]+)(?:\s+"([^"]*)")?\)/',
                function($m) use ($safe) {
                    $href = $this->filterEsc($safe ? $this->filterUrl($this->filterDec($m[2])) : $this->filterDec($m[2]));
                    $ttl  = isset($m[3]) ? ' title="'.$this->filterEsc($this->filterDec($m[3])).'"' : '';
                    return $this->addStash('<a href="'.$href.'"'.$ttl.'>'.$m[1].'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/<(https?:\/\/[^\s>]+)>/',
                fn($m) => $this->addStash('<a href="'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>/',
                fn($m) => $this->addStash('<a href="mailto:'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
                $src
            ) ?? $src;

            if ($safe) {
                $src = preg_replace_callback('/<[^>]+>/', fn($m) => $this->filterEsc($m[0]), $src) ?? $src;
            }

            $src = preg_replace(['/\*{3}(.+?)\*{3}/s', '/_{3}(.+?)_{3}/s'], '<strong><em>$1</em></strong>', $src);
            $src = preg_replace(['/\*{2}(.+?)\*{2}/s', '/_{2}(.+?)_{2}/s'], '<strong>$1</strong>', $src);
            $src = preg_replace(['/\*([^*\n]+)\*/', '/(?<![_\w])_([^_\n]+)_(?![_\w])/'], '<em>$1</em>', $src);
            $src = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $src);
            $src = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $src);
            $src = preg_replace(['/  \n/', '/\\\\\n/'], "<br>\n", $src);

            return $src;
        }
    };

    return $md->filterHtml($src, $safe, $mod);
}

# Backup DB for MySQL 8.0+ & MariaDB 10+
function addBackupDb(): bool {
    global $db, $conf;
    if (!$conf['security']['log_b']) return false;
    $backup_start = microtime(true);

    $sess_f = COUNTER_DIR.'/backup.log';
    $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
    $past = time() - intval($conf['security']['sess_b']);

    if ($sess_b >= $past) {
        return false; // Not yet time for Backup
    }

    // Timestamp-Datei aktualisieren
    if (file_exists($sess_f)) unlink($sess_f);
    $fp_time = fopen($sess_f, "wb");
    if ($fp_time) {
        fwrite($fp_time, time());
        fclose($fp_time);
    }

    // FIX: Memory-Management
    ini_set('memory_limit', '512M');

    // safe_mode ist entfernt; defensiv behandeln
    $safe = 0;
    if (function_exists('ini_get')) {
        $sm = ini_get('safe_mode');
        $safe = ($sm && $sm != '0') ? 1 : 0;
    }
    if (!$safe && function_exists("set_time_limit")) set_time_limit(600);

    # MySQL connection charset
    # auto - automatic (uses table charset), latin1, cp1251, utf8, etc.
    $ccharset = "auto";
    $charset = preg_replace('#[^a-zA-Z0-9_\\-]#', '', (string)$ccharset);

    # Table types where only structure is saved (no data), comma-separated
    $conlycreate = "MRG_MyISAM,MERGE,HEAP,MEMORY";

    # Table filter uses wildcard patterns. Supported special characters:
    # * - any number of characters;
    # ? - one arbitrary character;
    # ^ - excludes table(s) from the list.

    # Examples:
    # slaed_*           - all tables starting with "slaed_" (all Invision Board forum tables)
    # slaed_*, ^slaed_session  - all tables starting with "slaed_", except "slaed_session"
    # slaed_s*s, ^slaed_session - all tables starting with "slaed_s" and ending with "s", except "slaed_session"
    # ^*s               - all tables except those ending with "s"
    # ^slaed_????       - all tables except those starting with "slaed_" with 4 chars after the underscore
    $ctables = "^ipb_*";

    $bsize = 0;

    // Server-Version via PDO
    try {
        $vres = $db->getSqlQuery("SELECT VERSION() AS v");
        $vrow = $vres ? $vres->fetch(PDO::FETCH_ASSOC) : null;
        $ver = $vrow && isset($vrow['v']) ? $vrow['v'] : '0.0.0';
        preg_match("#^(\d+)\.(\d+)\.(\d+)#", $ver, $m);
        $bmysql_ver = isset($m[1]) ? sprintf("%d%02d%02d", $m[1], $m[2], $m[3]) : 0;
    } catch (Exception $e) {
        error_log("Backup failed: Cannot get MySQL version - " . $e->getMessage());
        return false;
    }

    $bonly_create = explode(",", $conlycreate);

    $btables_exclude = !empty($ctables) && $ctables[0] == '^' ? 1 : 0;
    $btables = (!empty($ctables)) ? $ctables : "";
    $btables = explode(",", $btables);
    $tbls = [];

    if (!empty($ctables)) {
        foreach($btables as $table) {
            $table = preg_replace("/[^\w*?^]/", "", $table);
            $pattern = ["/\?/", "/\*/"];
            $replace = [".", ".*?"];
            $tbls[] = preg_replace($pattern, $replace, $table);
        }
    }

    // Zeichenkodierung setzen, wenn nicht auto
    if ($bmysql_ver > 40101 && $charset !== '' && $charset != 'auto') {
        $db->getSqlQuery("SET NAMES '".$charset."'");
        $last_charset = $charset;
    } else {
        $last_charset = "";
    }

    // FIX: Korrigierte Filter-Logik
    $tables = [];
    $res = $db->getSqlQuery("SHOW TABLES");

    while ($row = $res->fetch(PDO::FETCH_NUM)) {
        $status = 0;

        if (!empty($tbls)) {
            foreach ($tbls as $table) {
                $exclude = preg_match("#^\^#", $table) ? true : false;

                if (!$exclude) {
                    if (preg_match("#^{$table}$#i", $row[0])) {
                        $status = 1; // Include
                    }
                }

                if ($exclude && preg_match("#{$table}$#i", $row[0])) {
                    $status = -1; // Exclude
                    break; // Sofort abbrechen wenn excluded
                }
            }

            // FIX: Korrekte Include/Exclude Logik
            if ($btables_exclude) {
                // Exclude mode: Take everything except status == -1
                if ($status != -1) {
                    $tables[] = $row[0];
                }
            } else {
                // Include-Modus: Nimm nur status == 1
                if ($status == 1) {
                    $tables[] = $row[0];
                }
            }
        } else {
            // Keine Filter = alle Tabellen
            $tables[] = $row[0];
        }
    }

    if (empty($tables)) {
        error_log("Backup failed: No tables found to backup");
        return false;
    }

    $tabs = count($tables);
    $res = $db->getSqlQuery("SHOW TABLE STATUS");
    $tabinfo = [];
    $tab_charset = [];
    $tab_type = [];
    $tabsize = [];
    $tabinfo[0] = 0;

    while ($item = $res->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($item['Name'], $tables)) {
            $item['Rows'] = empty($item['Rows']) ? 0 : $item['Rows'];
            $tabinfo[0] += $item['Rows'];
            $tabinfo[$item['Name']] = $item['Rows'];
            $bsize += $item['Data_length'];
            $tabsize[$item['Name']] = 1 + round(1048576 / ($item['Avg_row_length'] + 1));

            if (!empty($item['Collation']) && preg_match("#^([a-z0-9]+)_#i", $item['Collation'], $m)) {
                $tab_charset[$item['Name']] = $m[1];
            }

            $tab_type[$item['Name']] = isset($item['Engine']) ? $item['Engine'] : $item['Type'];
        }
    }

    // FIX: Path Traversal security vulnerability
    $safe_dbname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $conf['db']['name']);
    $name = $safe_dbname."_".date("Y-m-d_H-i-s");

    // FIX: Verzeichnis-Check
    $backup_dir = BACKUP_DIR.'/';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0750, true)) {
            error_log("Backup failed: Cannot create backup directory");
            return false;
        }
    }

    $filepath = $backup_dir.$name.'.sql';

    // FIX: Error handling for fopen
    $fp = fopen($filepath, "wb");
    if (!$fp) {
        error_log("Backup failed: Cannot create file " . $filepath);
        return false;
    }

    // Header schreiben
    fwrite($fp, "# DB: ".$conf['db']['name']."\n");
    fwrite($fp, "# Tables: ".$tabs."\n");
    fwrite($fp, "# Size: ".round($bsize / 1048576, 2)." MB\n");
    fwrite($fp, "# Lines: ".number_format($tabinfo[0], 0, ",", " ")."\n");
    fwrite($fp, "# Date: ".date("Y.m.d H:i:s")."\n\n");

    $db->getSqlQuery("SET SQL_QUOTE_SHOW_CREATE = 1");

    foreach ($tables as $table) {
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$table)) {
            continue;
        }
        // Add a comma before each next VALUES row (except first row and after split markers) Check
        if ($bmysql_ver > 40101 && isset($tab_charset[$table]) && $tab_charset[$table] != $last_charset) {
            if ($ccharset == "auto" && !empty($tab_charset[$table])) {
                $tcharset = preg_replace('#[^a-zA-Z0-9_\\-]#', '', (string)$tab_charset[$table]);
                if ($tcharset !== '') {
                    $db->getSqlQuery("SET NAMES '".$tcharset."'");
                    $last_charset = $tcharset;
                }
            }
        }

        $res = $db->getSqlQuery("SHOW CREATE TABLE `{$table}`");
        $tab = $res->fetch(PDO::FETCH_NUM);

        // For MariaDB 10+ do NOT use conditional comments
        if (isset($tab[1])) {
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n{$tab[1]};\n\n");
        }

        if (in_array($tab_type[$table], $bonly_create)) continue;

        $NumericColumn = [];
        $res = $db->getSqlQuery("SHOW COLUMNS FROM `{$table}`");
        $field = 0;
        while ($col = $res->fetch(PDO::FETCH_NUM)) {
            $NumericColumn[$field++] = preg_match("#^(\w*int|year)#", $col[1]) ? 1 : 0;
        }
        $fields = $field;

        $from = 0;
        $limit = $tabsize[$table];

        if ($tabinfo[$table] > 0) {
            $i = 0;
            fwrite($fp, "INSERT INTO `{$table}` VALUES");

            while ($res = $db->getSqlQuery("SELECT * FROM `{$table}` LIMIT ".intval($from).", ".intval($limit))) {
                $batch = 0;

                while ($row = $res->fetch(PDO::FETCH_NUM)) {
                    $batch++;
                    $i++;

                    // CRITICAL LIMIT: flush INSERT every 10000 rows to avoid memory pressure
                    if ($i > 1 && ($i - 1) % 10000 == 0) {
                        // Close previous INSERT and start a new one
                        fwrite($fp, ";\n\nINSERT INTO `{$table}` VALUES");
                    }

                    for ($k = 0; $k < $fields; $k++) {
                        if ($NumericColumn[$k]) {
                            $row[$k] = isset($row[$k]) ? $row[$k] : "NULL";
                        } else {
                            $row[$k] = isset($row[$k]) ? $db->getSqlValue($row[$k]) : "NULL";
                        }
                    }

                    // Add a comma before each next VALUES row (except first row and after split markers)
                    $is_first_in_block = ($i == 1) || (($i - 1) % 10000 == 0);
                    fwrite($fp, ($is_first_in_block ? "\n" : ",\n")."(".implode(",", $row).")");
                }

                if ($batch < $limit) break;
                $from += $limit;
            }

            fwrite($fp, ";\n\n");
        }
    }

    fclose($fp);
    if (!addCompress($backup_dir, $filepath, $name, 'auto', true)) return false;

    // Performance-Logging
    $duration = round(microtime(true) - $backup_start, 2);
    error_log("Backup completed: {$tabs} tables, ".round($bsize/1048576, 2)."MB in {$duration}s");
    return true;
}

# Get admin module names (stored as names)
function getAdminModuleNames(string $modules): array {
    $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
    return array_values(array_unique($list));
}

# Format head
function setHead(array $seo = []): void {
    global $db, $home, $index, $conf, $user, $admin, $name, $theme, $op;
    $name = $name ?? '';
    $ctime = time();
    $request = getenv('REQUEST_URI');
    if ($conf['session']) {
        $ip = getIp();
        $url = urlencode($request);
        $guest = 0;
        if (is_admin()) {
            $uname = text_filter(substr($admin[1], 0, 25), 1);
            $guest = 3;
        } elseif (!defined("ADMIN_FILE") && is_user()) {
            $uname = text_filter(substr($user[1], 0, 25), 1);
            $guest = 2;
        } elseif (!defined("ADMIN_FILE") && !is_user()) {
            $bname = is_bot();
            if ($bname) {
                $uname = text_filter(substr($bname, 0, 25), 1);
                $guest = 1;
            } else {
                $uname = $ip;
                $guest = 0;
            }
        }
        $sess_f = "config/counter/sess.txt";
        $sess_t = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($conf['sess_t']);
        if ($sess_t < $past) {
            $db->getSqlQuery("DELETE FROM ".PREFIX_DB."_session WHERE time < :past", ['past' => $past]);
            if (file_exists($sess_f)) unlink($sess_f);
            $fp = fopen($sess_f, "wb");
            fwrite($fp, $ctime);
            fclose($fp);
        }
        if (!empty($uname)) {
            if (!defined("ADMIN_FILE") && is_user()) {
                $uagent = getAgent();
                $uid= intval($user[0]);
                $db->getSqlQuery("UPDATE ".PREFIX_DB."_users SET user_last_ip = :ip, user_lastvisit = NOW(), user_agent = :agent WHERE user_id = :uid", ['ip' => $ip, 'agent' => $uagent, 'uid' => $uid]);
            }
            $num = $db->getSqlRowCount($db->getSqlQuery("SELECT id FROM ".PREFIX_DB."_session WHERE uname = :uname", ['uname' => $uname]));
            if ($num >= 1) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_session SET time = :time, host_addr = :ip, guest = :guest, module = :module, url = :url WHERE uname = :uname', [':time' => $ctime, ':ip' => $ip, ':guest' => $guest, ':module' => $name, ':url' => $url, ':uname' => $uname]);
            } else {
                $db->getSqlQuery("INSERT INTO ".PREFIX_DB."_session (uname, time, host_addr, guest, module, url) VALUES (:uname, :time, :ip, :guest, :module, :url)", ['uname' => $uname, 'time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'module' => $name, 'url' => $url]);
            }
        }
    }
    if ($conf['referers']['refer']) {
        $referer = get_referer();
        if ($referer) {
            $refer_f = "config/counter/refer.txt";
            $refer_t = (file_exists($refer_f) && filesize($refer_f) != 0) ? file_get_contents($refer_f) : 0;
            $past = $ctime - intval($conf['referers']['refer_t']);
            if ($refer_t < $past) {
                $db->getSqlQuery("DELETE FROM ".PREFIX_DB."_referer WHERE lid = :lid", ['lid' => 0]);
                unlink($refer_f);
                $fp = fopen($refer_f, "wb");
                fwrite($fp, $ctime);
                fclose($fp);
            }
            $ip = getIp();
            $uid = is_user() ? intval($user[0]) : 0;
            $link = text_filter($request);
            if (is_active('auto_links')) {
                list($exist) = $db->getSqlRow($db->getSqlQuery("SELECT ip FROM ".PREFIX_DB."_referer WHERE ip = :ip AND lid != :lid", ['ip' => $ip, 'lid' => 0]));
                if ($exist) {
                    if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, referer, link, date, lid) VALUES (:uid, :name, :ip, :referer, :link, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'link' => $link, 'lid' => 0]);
                } else {
                    $result = $db->getSqlQuery("SELECT link FROM ".PREFIX_DB."_auto_links");
                    while(list($slink) = $db->getSqlRow($result)) {
                        if (preg_match("#".$slink."#i", $referer)) {
                            $islink = 1;
                            break;
                        } else {
                            $islink = 0;
                        }
                    }
                    if ($islink) {
                        $db->getSqlQuery("UPDATE ".PREFIX_DB."_auto_links SET hits = hits + 1 WHERE link = :link", ['link' => $slink]);
                        list($lid) = $db->getSqlRow($db->getSqlQuery("SELECT id FROM ".PREFIX_DB."_auto_links WHERE link = :link", ['link' => $slink]));
                        $db->getSqlQuery("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, referer, link, date, lid) VALUES (:uid, :name, :ip, :referer, :link, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'link' => $link, 'lid' => $lid]);
                    } else {
                        if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, referer, link, date, lid) VALUES (:uid, :name, :ip, :referer, :link, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'link' => $link, 'lid' => 0]);
                    }
                }
            } else {
                if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, referer, link, date, lid) VALUES (:uid, :name, :ip, :referer, :link, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'link' => $link, 'lid' => 0]);
            }
        }
    }
    if ($conf['statistic']['stat']) {
        $sreferer = get_referer();
        $sreqhom = text_filter($request);
        $spath = COUNTER_DIR.'/';
        $slog = $spath.'statistic.log';
        $sdate = file_exists($slog) ? file($slog) : false;
        if ($sdate) {
            $con = explode('|', trim($sdate[0]));
            if (date('d.m.Y') != $con[0]) {
                $fpd = fopen($spath.'days.log', 'ab');
                flock($fpd, LOCK_EX);
                fwrite($fpd, $sdate[0].PHP_EOL);
                flock($fpd, LOCK_UN);
                fclose($fpd);
                if (file_exists($spath.'statistic.log')) unlink($spath.'statistic.log');
                if (file_exists($spath.'ips.log')) unlink($spath.'ips.log');
                if (file_exists($spath.'user.log')) unlink($spath.'user.log');
                if (substr($con[0], 3) != date('m.Y')) {
                    $month = date('Y-m', strtotime('-1 month'));
                    $sdir = $spath.'statistic';
                    if (!is_dir($sdir)) mkdir($sdir, 0755, true);
                    rename($spath.'days.log', $sdir.'/statistic_'.$month.'.log');
                    if (file_exists($spath.'days.log')) unlink($spath.'days.log');
                }
                $ahits = ($con[3] ?? 0) ? (($con[3] ?? 0) + 1) : '1';
                $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
                $srefer = ($sreferer) ? '1' : '0';
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
                $wc = date('d.m.Y').'|0|1|'.$ahits.'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            } else {
                $check = checkUniqueIp();
                $checku = check_user();
                $shost = ($check) ? intval(($con[1] ?? 0) + 1) : ($con[1] ?? 0);
                $sengine = ($check && $conf['session'] && $guest == 1) ? intval(($con[4] ?? 0) + 1) : ($con[4] ?? 0);
                $srefer = ($check && $sreferer) ? intval(($con[5] ?? 0) + 1) : ($con[5] ?? 0);
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? intval(($con[6] ?? 0) + 1) : ($con[6] ?? 0);
                $suser = ($checku && $conf['session'] && $guest == 2) ? intval(($con[7] ?? 0) + 1) : ($con[7] ?? 0);
                $wc = $con[0].'|'.$shost.'|'.intval(($con[2] ?? 0) + 1).'|'.intval(($con[3] ?? 0) + 1).'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|'.$suser;
            }
            $fps = fopen($spath.'statistic.log', 'wb');
            if (flock($fps, LOCK_EX)) {
                ftruncate($fps, 0);
                fwrite($fps, $wc);
                fflush($fps);
                flock($fps, LOCK_UN);
            }
            fclose($fps);
        } elseif (!file_exists($slog) || filemtime($slog) < strtotime('today midnight')) {
            if (file_exists($spath.'ips.log')) unlink($spath.'ips.log');
            if (file_exists($spath.'user.log')) unlink($spath.'user.log');
            $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
            $srefer = ($sreferer) ? '1' : '0';
            $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
            $wc = date('d.m.Y').'|0|1|1|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            $fps = fopen($slog, 'wb');
            flock($fps, LOCK_EX);
            fwrite($fps, $wc);
            flock($fps, LOCK_UN);
            fclose($fps);
        }
    }
    if ((!defined("ADMIN_FILE") && $conf['cache'] == 1) || (!defined("ADMIN_FILE") && $conf['cache'] == 2 && $home)) {
        ob_start();
        $url = str_replace('/', '', $request);
        $url = (!$url) ? 'index.php' : $url;
        if ($conf['cache'] == 2) {
            if ($conf['rewrite']) {
                $match = ($url == "index.php" || $url == "index.html") ? 1 : 0;
            } else {
                $match = ($url == "index.php") ? 1 : 0;
            }
        } else {
            if ($conf['rewrite']) {
                $match = ($url == "index.php" || $url == "index.html" || strstr($url, "index.php?name=".$name) || strstr($url, $name)) ? 1 : 0;
            } else {
                $match = ($url == "index.php" || strstr($url, "index.php?name=".$name)) ? 1 : 0;
            }
        }
        if ($match && !is_user() && !is_admin()) {
            $cacheurl = "config/cache/".md5($url).".txt";
            if (file_exists($cacheurl) && filesize($cacheurl) != 0 && ($ctime - $conf['cache_t']) < filemtime($cacheurl)) {
                readfile($cacheurl);
                exit;
            }
        }
    }
    $index = file_get_contents(getThemeFile('index'));
    if (defined('ADMIN_FILE') && ($conf['lic_h'] != 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt' || $conf['lic_f'] != 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==' || !preg_match('#{%LICENSE%}#', $index))) setExit(_NO_LICENSE);
    $licens = base64_decode($conf['lic_h']).date("Y").base64_decode($conf['lic_f']);
    $index = str_replace("{%LICENSE%}", $licens, $index);
    preg_match("#^(.*){%MODULE%}#iUs", $index, $head);
    $head = (isset($head[1])) ? $head[1] : die("Error in Head!");
    preg_match("#{%MODULE%}(.*)$#iUs", $index, $index);
    $index = (isset($index[1])) ? $index[1] : die("Error in Foot!");
    $strmeta = '<meta charset="'._CHARSET.'">'."\n";
    $strlink = $stscript = '';
    $sep = urldecode($conf['defis']);
    if (!defined('ADMIN_FILE')) {
        $atime  = date('Y-m-d H:i:s');
        $time   = $seo['time']   ?? $atime;
        $mtime  = $time;
        $title    = $seo['title']  ?? $conf['sitename'];
        $headline = $title;
        $desc   = $seo['desc']   ?? $conf['slogan'];
        $img    = ($seo['img'] ?? '') ?: $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'];
        $ctitle = $seo['ctitle'] ?? '';
        $author = $seo['author'] ?? $conf['sitename'];
        $url = ($conf['rewrite']) ? urldecode(substr($request, 1)) : urldecode(str_replace('index.php?', '', substr($request, 1)));
        $purl = ($conf['rewrite']) ? $conf['homeurl'].'/'.htmlspecialchars($url) : (($home) ? $conf['homeurl'] : $conf['homeurl'].'/index.php?'.htmlspecialchars($url));
        $type = 'article';
        if ($home) {
            $title = $conf['sitename'].' '.$sep.' '.$conf['slogan'];
        } else {
            if ($conf['ltitle']) {
                $mod = deflmconst($conf['name']);
                $title = ($title == $conf['sitename']) ? [] : [$title];
                $title = empty($ctitle) ? $title : array_merge($title, array($ctitle));
                $word = getVar('get', 'word', 'word');
                $title = empty($word) ? $title : array_merge($title, array($word));
                $let = getVar('get', 'let', 'let');
                $title = empty($let) ? $title : array_merge($title, array($let));
                $num = getVar('get', 'num', 'num');
                $title = empty($num) ? $title : array_merge($title, array(_PAGE.' '.$num));
                $com = getVar('get', 'com', 'num');
                $title = empty($com) ? $title : array_merge($title, array(_COMMENTS.' '.$com));
                if ($op == 'best') {
                    $title = array_merge($title, array(_BEST));
                } elseif ($op == 'pop') {
                    $title = array_merge($title, array(_POP));
                } elseif ($op == 'liste') {
                    $title = array_merge($title, array(_LIST));
                } elseif ($op == 'add') {
                    $title = array_merge($title, array(_ADD));
                }
                $title = array_merge($title, array($mod));
                $title = array_merge($title, array($conf['sitename']));
                $title = implode(' '.$sep.' ', array_map('trim', $title));
            }
        }
        $strmeta .= '<title>'.$title.'</title>'."\n"
        .'<meta name="author" content="'.$conf['sitename'].'">'."\n"
        .'<meta name="description" content="'.$desc.'">'."\n"
        .'<meta name="robots" content="index, follow">'."\n"
        .'<meta name="revisit-after" content="1 days">'."\n"
        .'<meta name="rating" content="general">'."\n"
        .'<meta name="generator" content="SLAED CMS">'."\n";
        $seofrom = ['[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]', '[headline]', '[author]'];
        $seoto   = [$conf['homeurl'], $conf['sitename'], $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'], _LOCALE, date('c', strtotime($time)), date('c', strtotime($mtime)), $title, $desc, $img, $ctitle, $type, $purl, $headline, $author];
        if (!empty($conf['agraph']) && !empty($conf['graph'])) {
            $strmeta .= str_replace($seofrom, $seoto, $conf['graph']);
        }
        $strlink .= '<link rel="shortcut icon" href="templates/'.$theme.'/favicon.png">'."\n";
        if (strpos($conf['homeurl'], get_host()) !== false) $strlink .= '<link rel="canonical" href="'.$purl.'">'."\n";
        if ($conf['rss']['act']) {
            $fieldc = explode('||', $conf['rss']['rss']);
            foreach ($fieldc as $val) {
                if ($val != '') {
                    $out = explode('|', $val);
                    if ($out[0] != '0' && $out[1] != '0' && $out[2] == '1') $strlink .= '<link rel="alternate" type="application/rss+xml" href="'.$out[1].'" title="'.$out[0].'">'."\n";
                }
            }
        }
        $strlink .= '<link rel="search" type="application/opensearchdescription+xml" href="'.$conf['homeurl'].'/index.php?go=search" title="'.$conf['sitename'].' - '._SEARCH.'">'."\n";
    } else {
        $strmeta .= '<title>'.$conf['sitename'].' '.$sep.' '._ADMIN.'</title>'."\n";
    }
    $strlink .= doCss();
    if (!defined('ADMIN_FILE') && !empty($conf['aschema']) && !empty($conf['schema'])) {
        $stscript = str_replace($seofrom, $seoto, $conf['schema']);
    }
    $script = (defined('ADMIN_FILE') || empty($conf['script_b'])) ? doScript()."\n".$stscript : $stscript;
    $head = str_replace(['{%META%}', '{%LINK%}', '{%SCRIPT%}'], [$strmeta, $strlink, $script], addblocks($head));
    $cron = 0;
    if ($conf['security']['log_d']) {
        $sess_f = 'config/counter/dump.txt';
        $sess_d = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($conf['security']['sess_d']);
        if ($sess_d < $past) {
            $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'filereport', 'go=3&amp;op=filereport', ''); return false;\"$1>", $head);
            $cron = 1;
        } else {
            $cron = 0;
        }
    }
    if ($conf['security']['log_b'] && !$cron) {
        $sess_f = COUNTER_DIR.'/backup.log';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($conf['security']['sess_b']);
        if ($sess_b < $past) {
            $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'backup', 'go=3&amp;op=backup', ''); return false;\"$1>", $head);
            $cron = 1;
        } else {
            $cron = 0;
        }
    }
    if (!empty($conf['sitemap']['auto']) && !$cron) {
        $sess_f = 'sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = $ctime - intval($conf['sitemap']['auto_t'] ?? 0);
        if ($sess_b < $past) {
            $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'sitemap', 'go=3&amp;op=sitemap', ''); return false;\"$1>", $head);
            $cron = 1;
        } else {
            $cron = 0;
        }
    }
    if ($conf['newsletter'] && !$cron) {
        $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'newsletter', 'go=3&amp;op=newsletter', ''); return false;\"$1>", $head);
    }
    echo setTemplateHead($head);
    unset($head);
    if (!defined('ADMIN_FILE')) update_points(1);
}

# Format foot
function setFoot(): void {
 global $home, $name, $index, $conf, $do_gzip_compress;
    $index = addblocks($index);
    $index = (!defined('ADMIN_FILE') && !empty($conf['script_b'])) ? str_replace('{%SCRIPT%}', doScript(), $index) : str_replace('{%SCRIPT%}', '', $index);
    echo setTemplateFoot($index);
    unset($index);
    if ((!defined('ADMIN_FILE') && $conf['cache'] == 1) || (!defined('ADMIN_FILE') && $conf['cache'] == 2 && $home)) {
        $dir = 'config/cache/';
        $url = str_replace('/', '', getenv('REQUEST_URI'));
        $url = (!$url) ? 'index.php' : $url;
        if ($conf['cache'] == 2) {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html') ? 1 : 0;
            } else {
                $match = ($url == 'index.php') ? 1 : 0;
            }
        } else {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html' || strstr($url, 'index.php?name='.$name) || strstr($url, $name)) ? 1 : 0;
            } else {
                $match = ($url == 'index.php' || strstr($url, 'index.php?name='.$name)) ? 1 : 0;
            }
        }
        $cont = ob_get_contents();
        if ($cont && $match && !is_user() && !is_admin()) {
            $cont = ($conf['cache_c']) ? getCompressHtml($cont) : $cont;
            $fp = fopen($dir.md5($url).'.txt', 'wb');
            fwrite($fp, $cont);
            fclose($fp);
        }
        if (!empty($conf['cache_d'])) {
            $time = time();
            $expire = $conf['cache_d'] * 86400;
            if (is_dir($dir)) {
                if ($dh = opendir($dir)) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != '.' && $file != '..' && $file != '.htaccess' && $file != 'index.html') {
                            $ftime = $time - filemtime($dir.$file);
                            if ($ftime >= $expire) unlink($dir.$file);
                        }
                    }
                    closedir($dh);
                }
            }
        }
    }
    while (ob_get_level() > 0) ob_end_flush();
    exit;
}

# Safe redirect with optional referer fallback
function setRedirect(string $url, bool $refer = false, int $code = 302): never {
    if (!in_array($code, [301, 302, 303, 307, 308], true)) $code = 302;
    if ($code === 302 && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') $code = 303;
    $target = trim(str_replace(["\r", "\n"], '', $url));
    if ($refer && (isset($_GET['refer']) || isset($_POST['refer']))) {
        $ref = trim(str_replace(["\r", "\n"], '', (string)($_SERVER['HTTP_REFERER'] ?? getenv('HTTP_REFERER') ?? '')));
        $valid = $ref !== '' && !preg_match('#^unknown#i', $ref) && !preg_match('#^bookmark#i', $ref);
        if ($valid) {
            $is_rel = str_starts_with($ref, '/') && !str_starts_with($ref, '//');
            if ($is_rel) {
                $target = $ref;
            } else {
                $rschm = strtolower((string)(parse_url($ref, PHP_URL_SCHEME) ?? ''));
                $rhost = (string)(parse_url($ref, PHP_URL_HOST) ?? '');
                $chost = (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
                $is_same = in_array($rschm, ['http', 'https'], true) && $rhost !== '' && $chost !== '' && strcasecmp($rhost, $chost) === 0;
                if ($is_same) $target = $ref;
            }
        }
    }
    if ($target === '') $target = '/';
    header('Location: '.$target, true, $code);
    exit;
}

# Highlights text terms inside HTML content
function filterTextHighlight(string $sourse, string $word): string {
    $word = var_filter(urldecode($word));
    if (!$word) return $sourse;
    $word = preg_replace('/\s+/', ' ', trim($word));
    $warray = strpos($word, ' ') !== false ? explode(' ', $word) : [$word];
    preg_match_all('#<[^>]*>#', $sourse, $tags);
    $taglist = [];
    $k = 0;
    foreach ($tags[0] as $tag) {
        $k++;
        $taglist[$k] = $tag;
        $sourse = str_replace($tag, '<'.$k.'>', $sourse);
    }
    foreach ($warray as $i) {
        $i = trim($i);
        if ($i === '') continue;
        $pattern = '/'.preg_quote($i, '/').'/iu';
        $sourse = preg_replace($pattern, '<span class="sl_word">$0</span>', $sourse);
    }
    foreach ($taglist as $k => $tag) $sourse = str_replace('<'.$k.'>', $tag, $sourse);
    return $sourse;
}

# Write, append, or compress file
function addFile(string $file, string $src, string $comp = 'none', bool $del = false, string $mode = 'w', int $max = 10485760): int {
    if (is_file($src)) {
        $data = file_get_contents($src);
        if ($data === false) {
            addErrorFile(_ERR_READ.': '.$src);
            return 1;
        }
    } else {
        $data = $src;
    }
    $flags = ($mode === 'a' ? FILE_APPEND : 0) | LOCK_EX;
    if (file_put_contents($file, $data, $flags) === false) {
        addErrorFile(_ERR_WRITE.': '.$file);
        return 2;
    }
    if ($comp !== 'none') return addCompress(dirname($file), $file, basename($file), $comp, filesize($file) > $max || $del) ? 0 : 3;
    return 0;
}

# Secure recursive directory deletion
function deleteDir(string $dir): bool {
    if (!file_exists($dir)) return false;
    if (!is_dir($dir)) return unlink($dir);
    $files = scandir($dir);
    if ($files === false) return false;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = realpath($dir.DIRECTORY_SEPARATOR.$file);
        if ($path === false || !deleteDir($path)) return false;
    }
    return rmdir($dir);
}

# Check which compression methods are available
function checkCompress(): array {
    return ['zip' => class_exists('ZipArchive'), 'gz' => function_exists('gzopen'), 'bz2' => function_exists('bzopen')];
}

# Check if IP exists in log, add once if missing
function checkUniqueIp(): bool {
    $file = COUNTER_DIR.'/ips.log';
    $ip = getIp();
    if (file_exists($file)) {
        $cont = file_get_contents($file);
        if ($cont === false) {
            addErrorFile(_ERR_READ.': '.$file);
            return false;
        }
        if ($cont !== '' && str_contains(','.$cont, ','.$ip.',')) return false;
    }
    addFile($file, $ip.',', 'none', false, 'a');
    return true;
}

# Compress a file, folder or string (zip, gz, bz2)
function addCompress(string $dir, string $src, string $name, string $mode = 'auto', bool $del = false, bool $bak = false): bool {
    if (!is_dir($dir) || !is_writable($dir)) {
        addErrorFile(_ERR_DIR.': '.$dir);
        return false;
    }
    if (empty($src) || empty($name)) {
        addErrorFile(_ERR_PARAM);
        return false;
    }
    $name = basename($name);
    $avail = checkCompress();
    $algo = match (strtolower($mode)) {
        'auto' => $avail['zip'] ? 'zip' : ($avail['gz'] ? 'gz' : ($avail['bz2'] ? 'bz2' : 'none')),
        'zip' => 'zip',
        'gz', 'gzip' => 'gz',
        'bz2', 'bzip2' => 'bz2',
        default => 'invalid'
    };
    if ($algo === 'none') {
        if ($bak && is_file($src)) return rename($src, rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.'.bak');
        addErrorFile(_ERR_NOCOMP);
        return false;
    }
    if ($algo === 'invalid') {
        addErrorFile(_ERR_INVMODE.': '.$mode);
        return false;
    }
    if (!$avail[$algo]) {
        $errmsg = match($algo) { 'zip' => _ERR_ZIPNA, 'gz' => _ERR_GZNA, 'bz2' => _ERR_BZ2NA };
        addErrorFile($errmsg);
        return false;
    }
    $exts = match($algo) {'zip' => '.zip', 'gz' => '.gz', 'bz2' => '.bz2' };
    $nbase = preg_replace('/\.(zip|gz|bz2)$/i', '', $name);
    $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$nbase.$exts;

    if ($algo === 'zip') {
        $zip = new ZipArchive();
        $res = $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            addErrorFile(_ERR_ZOPEN.': '.$file);
            return false;
        }

        // Handle directory
        if (is_dir($src)) {
            $rit = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            $base = strlen(rtrim($src, DIRECTORY_SEPARATOR)) + 1;

            foreach ($rit as $info) {
                $path = $info->getRealPath();
                $local = substr($path, $base);

                if (!$zip->addFile($path, $local)) {
                    $zip->close();
                    addErrorFile(_ERR_ZADD.': '.$path);
                    return false;
                }
            }
        }
        // Handle file
        elseif (is_file($src)) {
            if (!$zip->addFile($src, basename($src))) {
                $zip->close();
                addErrorFile(_ERR_ZADD.': '.$src);
                return false;
            }
        }
        // Handle string content
        else {
            $iname = $nbase.'.txt';
            if (!$zip->addFromString($iname, $src)) {
                $zip->close();
                addErrorFile(_ERR_ZADD.': '.$iname);
                return false;
            }
        }

        $zip->close();

        // Delete source if requested
        if ($del) {
            if (is_file($src)) {
                if (!unlink($src)) addErrorFile(_ERR_DELETE.': '.$src);
            } elseif (is_dir($src)) {
                if (!deleteDir($src)) {
                    addErrorFile(_ERR_DELETE.': '.$src);
                    return false;
                }
            }
        }

        return true;
    }
    
    // GZ and BZ2 only support single files
    if (!is_file($src)) {
        addErrorFile(_ERR_FILE.': '.$src);
        return false;
    }

    $srcf = fopen($src, 'rb');
    if (!$srcf) {
        addErrorFile(_ERR_OPEN.': '.$src);
        return false;
    }

    if ($algo === 'gz') {
        $zipf = gzopen($file, 'wb');
        if (!$zipf) {
            fclose($srcf);
            addErrorFile(_ERR_GZIP.': '.$file);
            return false;
        }

        while (!feof($srcf)) {
            $chunk = fread($srcf, 65536);
            if ($chunk === false) {
                gzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_READ.': '.$src);
                return false;
            }
            if (gzwrite($zipf, $chunk) === false) {
                gzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_GZIP.': Write failed');
                return false;
            }
        }

        gzclose($zipf);
        fclose($srcf);
    }
    elseif ($algo === 'bz2') {
        $zipf = bzopen($file, 'wb');
        if (!$zipf) {
            fclose($srcf);
            addErrorFile(_ERR_BZIP.': '.$file);
            return false;
        }

        while (!feof($srcf)) {
            $chunk = fread($srcf, 65536);
            if ($chunk === false) {
                bzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_READ.': '.$src);
                return false;
            }
            if (bzwrite($zipf, $chunk) === false) {
                bzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_BZIP.': Write failed');
                return false;
            }
        }

        bzclose($zipf);
        fclose($srcf);
    }
    else {
        fclose($srcf);
        addErrorFile(_ERR_TYPE.': '.$algo);
        return false;
    }

    // Delete source if requested
    if ($del) {
        if (!unlink($src)) addErrorFile(_ERR_DELETE.': '.$src);
    }

    return true;
}

# Error logging with rotation and compression
function addErrorFile(string $msg): bool {
 global $conf;
    static $running = false;
    if ($running) {
        error_log('[LOG] Recursive call prevented: '.$msg);
        return false;
    }
    $running = true;
    $log = LOGS_DIR.'/error_file.log';
    $cfg = $conf['security'] ?? [];
    $max = $cfg['log_size'] ?? 10485760;
    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    if (file_put_contents($log, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[LOG] Write failed: '.$log.' | '.$msg);
        $running = false;
        return false;
    }
    if (filesize($log) >= $max) {
        $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
        addCompress(dirname($log), $log, $safe, 'auto', true, true);
    }
    $running = false;
    return true;
}

# Captcha check
function checkCaptcha(int $id): bool {
 global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $recaptcha = getVar('post', 'recaptcha', 'text');
        if ($recaptcha) {
            $url = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$conf['capsec'].'&response='.$recaptcha.'&remoteip='.getIp());
            $ret = json_decode($url, true);
            $cont = ($ret['success'] == 1 && substr($ret['score'], 2) >= $conf['quality']) ? false : true;
        } else {
            $cont = true;
        }
    } else {
        $cont = false;
    }
    return $cont;
}

# Is there any content in the array
function isArray(mixed $arr): bool {
    if (!is_array($arr)) return !empty($arr);
    foreach ($arr as $a) {
        if (isArray($a)) return true;
    }
    return false;
}

# Generating categories for modules
function setCategories(string $mod, int $sub, bool $desc, string $id = ''): string {
 global $db, $user, $conf, $locale;
    if (analyze($mod)) {
        $id = (intval($id)) ? $id : 0;
        $params = ['mod' => $mod];
        if ($id) {
            $where = "WHERE modul = :mod AND parentid = :pid";
            $params['pid'] = $id;
        } elseif ($conf['multilingual']) {
            $where = "WHERE modul = :mod AND (language = :loc OR language = '')";
            $params['loc'] = $locale;
        } else {
            $where = "WHERE modul = :mod";
        }
        $cnum = 0;
        $result = $db->getSqlQuery("SELECT id, title, description, img, parentid, auth_view, auth_read FROM ".PREFIX_DB."_categories ".$where." ORDER BY ordern, title", $params);
        while (list($cid, $title, $description, $img, $parentid, $auth_view, $auth_read) = $db->getSqlRow($result)) {
            $massiv[] = [$cid, $title, $description, $img, $parentid, $auth_view, $auth_read];
            unset($cid, $title, $description, $img, $parentid, $auth_view, $auth_read);
            $cnum++;
        }
        if ($massiv) {
            $cont = '';
            foreach ($massiv as $val) {
                if ($val[4] == $id && is_acess($val[5])) {
                    $catid[] = $val[0];
                    $val[1] = defconst($val[1]);
                    $val[2] = defconst($val[2]);
                    if (is_acess($val[6])) {
                        $style = '';
                        $href = getSeoUrl(['name' => $mod, 'cat' => $val[0]]);
                        $ilink = ($val[3]) ? '<a href="'.$href.'" title="'.$val[1].'"><img src="'.img_find('categories/'.$val[3]).'" alt="'.$val[1].'" title="'.$val[1].'"></a>' : '<a href="'.$href.'" title="'.$val[1].'" class="sl_cat"></a>';
                        $alink = '<a href="'.$href.'" title="'.$val[1].'"><b>'.$val[1].'</b></a>';
                    } else {
                        $style = ' sl_hidden';
                        $htitle = $val[1].' - '._CCLOSED;
                        $ilink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'.$htitle.'" title="'.$htitle.'">' : '<span title="'.$htitle.'" class="sl_cat"></span>';
                        $alink = '<b>'.$val[1].'</b>';
                    }
                    $subcat = '';
                    foreach ($massiv as $sval) {
                        if ($val[0] == $sval[4] && is_acess($sval[5])) {
                            $catid[] = $sval[0];
                            if ($sub == 1) {
                                $sval[1] = defconst($sval[1]);
                                $shref = getSeoUrl(['name' => $mod, 'cat' => $sval[0]]);
                                $sublink = (is_acess($sval[6])) ? ' <a href="'.$shref.'" title="'.$sval[1].'" class="sl_cat">'.$sval[1].'</a>' : '';
                                $subcat .= '<div>'.$sublink.'</div>';
                            }
                        }
                    }
                    $description = ($desc) ? '<br><i>'.$val[2].'</i>' : '';
                    $cont .= '<div class="sl_catflex-box'.$style.'"><div class="sl_catflex-inbox"><div>'.$ilink.'</div><div>'.$alink.$description.'</div></div>'.$subcat.'</div>';
                }
            }
        }
        if ($cont) {
            $cat_ids = array_values(array_unique(array_map('intval', $catid)));
            $cat_ids = array_values(array_filter($cat_ids, static fn($v) => $v > 0));
            if (!$cat_ids) return '';
            $pp = [];
            $pm = [];
            foreach ($cat_ids as $k => $v) {
                $ph = 'c'.$k;
                $pp[] = ':'.$ph;
                $pm[$ph] = $v;
            }
            $cin = implode(', ', $pp);
            if ($mod == 'faq') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(fid) FROM ".PREFIX_DB."_faq WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INFA;
            } elseif ($mod == 'files') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(lid) FROM ".PREFIX_DB."_files WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INF;
            } elseif ($mod == 'help') {
                $uid = intval($user[0]);
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(sid) FROM ".PREFIX_DB."_help WHERE catid IN (".$cin.") AND time <= NOW() AND pid = '0' AND uid = :uid", array_merge($pm, ['uid' => $uid])));
                $in = _INH;
            } elseif ($mod == 'jokes') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(jokeid) FROM ".PREFIX_DB."_jokes WHERE cat IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INJ;
            } elseif ($mod == 'links') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(lid) FROM ".PREFIX_DB."_links WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INL;
            } elseif ($mod == 'media') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(id) FROM ".PREFIX_DB."_media WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INM;
            } elseif ($mod == 'news') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(sid) FROM ".PREFIX_DB."_news WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INN;
            } elseif ($mod == 'pages') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(pid) FROM ".PREFIX_DB."_pages WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INP;
            } elseif ($mod == 'shop') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(id) FROM ".PREFIX_DB."_products WHERE cid IN (".$cin.") AND time <= NOW() AND active != '0'", $pm));
                $in = _INS;
            }
            return setTemplateBasic('categories', ['{%categories%}' => _CATEGORIES, '{%content%}' => $cont, '{%total%}' => _ALLIN, '{%pages%}' => $pnum, '{%in%}' => $in, '{%cat%}' => $cnum, '{%category%}' => _ALLINC, '{%mod%}' => $mod]);
        }
    }
    return '';
}

# Generation of article numbers
function setArticleNumbers(string $name, string $mod, int $limit, string $url, string $cntfld, string $tbl, string $catfld = '', string $where = '', int $maxpg = 10, array $params = []): string {
    global $db, $conf, $locale;
    if (!defined('ADMIN_FILE') && $catfld && $where) {
        if ($conf['multilingual']) {
            $lng_where = 'WHERE modul = :mod AND (language = :loc OR language = \'\')';
            $lng_params = ['mod' => $mod, 'loc' => $locale];
        } else {
            $lng_where = 'WHERE modul = :mod';
            $lng_params = ['mod' => $mod];
        }
        $res = $db->getSqlQuery('SELECT id, auth_read FROM '.PREFIX_DB.'_categories '.$lng_where.' ORDER BY id', $lng_params);
        $catid = [];
        while (list($cid, $auth) = $db->getSqlRow($res)) {
            if (is_acess($auth)) $catid[] = (int)$cid;
        }
        $where = (!empty($catid)) ? ' WHERE '.$catfld.' IN ('.implode(', ',$catid).') AND '.$where : ' WHERE '.$where;
    } else {
        $where = $where ? ' WHERE '.$where : '';
    }
    $sql = 'SELECT COUNT('.$cntfld.') FROM '.PREFIX_DB.$tbl.$where;
    list($cnt) = $db->getSqlRow($db->getSqlQuery($sql,$params));
    $pages = $cnt > 0 ? (int)ceil($cnt / $limit) : 1;
    return setPageNumbers($name, $mod, $cnt, $pages, $limit, $url, $maxpg);
}

# Generation of page numbers
function setPageNumbers(string $tpl, string $mod, int $count, int $pages, int $limit, string $url = '', int $maxpg = 8, int $num = 0, string $anchor = '', string $n = 'num'): string {
    global $afile;
    $num  = $num ?: getVar('get', $n, 'num', 1);
    $nnum = $maxpg + 1;
    if ($pages > 1) {
        $cont = '';
        if ($num > 1) {
            $prev  = $num - 1;
            $cprev = (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $prev]).$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$prev.$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>';
        } else {
            $cprev = '<span class="sl_num" title="'._BACK.'">'._BACK.'</span>';
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= '<span title="'.$i.'">'.$i.'</span>';
            } else {
                if ((($i > ($num - $maxpg)) && ($i < ($num + $maxpg))) || ($i == $pages) || ($i == 1)) $cont .= (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $i]).$anchor.'" title="'.$i.'">'.$i.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$i.$anchor.'" title="'.$i.'">'.$i.'</a>';
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $maxpg))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
                if (($num < ($pages - $maxpg)) && ($i == ($pages - 1))) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
            }
        }
        if ($num < $pages) {
            $next  = $num + 1;
            $cnext = (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $next]).$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$next.$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>';
        } else {
            $cnext = '<span class="sl_num" title="'._NEXT.'">'._NEXT.'</span>';
        }
        return setTemplateBasic($tpl, ['{%overall%}' => _OVERALL, '{%count%}' => $count, '{%by%}' => _BY, '{%pages%}' => $pages, '{%page_s%}' => _PAGE_S, '{%page%}' => $limit, '{%perpage%}' => _PERPAGE, '{%pager%}' => $cont, '{%prev%}' => $cprev, '{%next%}' => $cnext]);
    }
    return '';
}

# Browser caching
function setCache($id=''): void {
    header('Content-Type: text/html; charset='._CHARSET);
    if ($id === "1") {
 global $conf;
        $cached = (int) ($conf['cache_d'] ?? 7);
        $max = $cached * 86400;
        $expires = time() + $max;
        header('Cache-Control: public, max-age='.$max);
        header('Expires: '.gmdate('D, d M Y H:i:s', $expires).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    }
    header('X-Powered-By: SLAED CMS');
    header('X-Powered-CMS: SLAED CMS');
}

# Set cached script file
function setScript(): void {
    header('Content-type: text/javascript');
    readfile('config/cache/'.md5(getTheme().'script').'.txt');
}

# Set cached CSS file
function setCss(): void {
    header('Content-type: text/css');
    readfile('config/cache/'.md5(getTheme().'style').'.txt');
}

# Set bottom navigation
function setNaviLower(string $mod): string {
    return setTemplateBasic('open').'<span class="sl_pos_center"><a href="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_foot">'._BACK.'</a><a href="index.php?name='.$mod.'" title="'._PAGEHOME.'" class="sl_but_foot">'._PAGEHOME.'</a><a OnClick="Upper(\'html, body\', 600);" title="'._PAGETOP.'" class="sl_but_foot">'._PAGETOP.'</a></span>'.setTemplateBasic('close');
}

# Load configuration file or directory and return chmod warning if needed
function checkPerms(string $fp): string {
    $perm = is_dir($fp) ? 777 : 666;
    $info = checkFileChmod($fp, $perm);
    return ($info !== '') ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $info]) : '';
}

# Check file chmod permission and try to fix it (Linux only)
function checkFileChmod(string $dir, int $chm): string {
    $out = '';
    if (file_exists($dir) && $chm > 0) {
        $per=substr(decoct(fileperms($dir)), -3);
        if (php_uname('s') === 'Linux' && $per != $chm) {
            $tdir = CONFIG_DIR.'/chmod.php';
            $mode = octdec((string)$chm);
            $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
            if (file_put_contents($tdir, '') !== false) {
                $own = (int)fileowner($tdir);
                $can = ($uid > -1) ? ($own === $uid) : is_writable($tdir);
                if ($can && is_writable($tdir)) chmod($tdir, $mode);
                $tper = substr(decoct(fileperms($tdir)), -3);
                if ($tper == $chm) {
                    $down = (int)fileowner($dir);
                    $cdir = ($uid > -1) ? ($down === $uid) : is_writable($dir);
                    if ($cdir && is_writable($dir)) chmod($dir, $mode);
                    $per = substr(decoct(fileperms($dir)),-3);
                }
                unlink($tdir);
            }
        }
        $out = ($per != $chm) ? $dir.' '._ERRORPERM.' CHMOD - '.$chm : '';
    }
    return $out;
}

# Saving configurations to a file
function setConfigFile(string $fp, array $arr, array $act = []): void {
    static $reserved = ['system.php', 'header.php', 'chmod.php', 'local.php'];
    if (in_array($fp, $reserved)) return;
    $fp = CONFIG_DIR.'/'.$fp;
    if (!empty($act)) $arr = array_replace_recursive($act, $arr);
    ksort($arr);
    $norm = function ($val) use (&$norm) {
        if (is_array($val)) {
            foreach ($val as $k => $vv) $val[$k] = $norm($vv);
            return $val;
        }
        return is_bool($val) ? (string)(int)$val : (string)$val;
    };
    foreach ($arr as $key => $val) $arr[$key] = $norm($val);
    $key  = pathinfo(basename($fp), PATHINFO_FILENAME);
    $data = ($key === 'global') ? $arr : [$key => $arr];
    $exp  = function (array $arr, int $dep = 0) use (&$exp): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['.PHP_EOL;
        foreach ($arr as $key => $val) {
            $out .= $ind.var_export($key, true).' => ';
            $out .= is_array($val) ? $exp($val, $dep + 1) : var_export($val, true);
            $out .= ','.PHP_EOL;
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'.PHP_EOL
    .'# Author: Eduard Laas'.PHP_EOL
    .'# Copyright (c) 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'return '.$exp($data).';'.PHP_EOL;
    file_put_contents($fp, $cnt, LOCK_EX);
}

# Definition and processing of header scripts files
function doScript(): string {
 global $theme, $conf;
    $async = ($conf['script_a']) ? 'async ' : '';
    $sfile = 'config/cache/'.md5($theme.'script').'.txt';
    $array = explode(',', $conf['script_f']);
    $array = is_array($array) ? $array : array();
    $array = (!$conf['security']['error_java']) ? array_merge($array, array('plugins/system/block-error.js')) : $array;
    if (!defined('ADMIN_FILE')) {
        if ($conf['cache_script'] && file_exists($sfile) && filesize($sfile) != 0 && (time() - $conf['cache_t']) < filemtime($sfile)) {
            $cont = ($conf['script_h']) ? file_get_contents($sfile) : '<script '.$async.'src="index.php?go=script"></script>';
        } else {
            foreach ($array as $file) {
                if (file_exists($file)) {
                    if ($conf['cache_script'] || $conf['script_h']) {
                        $cont = file_get_contents($file);
                        $arr[] = ($conf['script_c']) ? getCompressCode($cont) : $cont;
                    } else {
                        $arr[] = '<script '.$async.'src="'.$file.'"></script>';
                    }
                }
            }
            $cont = ($conf['script_h']) ? '<script>'.implode(' ', $arr).'</script>' : (($conf['cache_script']) ? implode(' ', $arr) : implode("\n", $arr));
            if ($conf['cache_script']) {
                file_put_contents($sfile, $cont);
                $cont = (file_exists($sfile) && !$conf['script_h']) ? '<script '.$async.'src="index.php?go=script"></script>' : $cont;
            }
        }
        if (file_exists('config/header.php')) {
            ob_start();
            include('config/header.php');
            $cont .= ob_get_clean();
        }
    } else {
        foreach ($array as $file) {
            if (file_exists($file)) {
                $arr[] = '<script '.$async.'src="'.$file.'"></script>';
            }
        }
        $cont = implode("\n", $arr);
    }
    return $cont;
}

# Definition and processing of CSS files
function doCss(): string {
 global $theme, $conf;
    $array = explode(',', str_replace('[theme]', $theme, $conf['css_f']));
    if (is_array($array)) {
        if (!defined('ADMIN_FILE')) {
            $cfile = 'config/cache/'.md5($theme.'style').'.txt';
            if ($conf['cache_css'] && file_exists($cfile) && filesize($cfile) != 0 && (time() - $conf['cache_t']) < filemtime($cfile)) {
                $cont = ($conf['css_h']) ? file_get_contents($cfile) : '<link rel="stylesheet" href="index.php?go=css">';
            } else {
                foreach ($array as $dir) {
                    foreach (glob($dir.'*.css') as $file) {
                        if (file_exists($file)) {
                            if ($conf['cache_css'] || $conf['css_h']) {
                                $cont = str_replace('../', '', file_get_contents($file));
                                $cont = preg_replace('#url\((\'|"|)(.*?)(\'|"|)\)#i', 'url('.$dir.'\\2)', $cont);
                                if ($conf['css_e']) $cont = preg_replace_callback('#url\((.*?\.(png|jpg|jpeg|gif|svg|bmp))\)#i', 'getImgEncode', $cont);
                                $arr[] = ($conf['css_c']) ? getCompressCss($cont) : $cont;
                            } else {
                                $arr[] = '<link rel="stylesheet" href="'.$file.'">';
                            }
                        }
                    }
                }
                $cont = ($conf['css_h']) ? '<style type="text/css">'.implode(' ', $arr).'</style>' : (($conf['cache_css']) ? implode(' ', $arr) : implode("\n", $arr));
                if ($conf['cache_css']) {
                    file_put_contents($cfile, $cont);
                    $cont = (file_exists($cfile) && !$conf['css_h']) ? '<link rel="stylesheet" href="index.php?go=css">' : $cont;
                }
            }
        } else {
            foreach ($array as $dir) {
                foreach (glob($dir.'*.css') as $file) {
                    if (file_exists($file)) {
                        $arr[] = '<link rel="stylesheet" href="'.$file.'">';
                    }
                }
            }
            $cont = implode("\n", $arr);
        }
    } else {
        $cont = '';
    }
    return $cont;
}

# Create a sitemap
function doSitemap(): void {
 global $db, $conf;
    if (defined('ADMIN_FILE') || !empty($conf['sitemap']['auto'])) {
        $sess_f = 'sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = time() - intval($conf['sitemap']['auto_t'] ?? 0);
        if (defined('ADMIN_FILE') || $sess_b < $past) {
            $date = date('Y-m-d');
            $modules_raw = (string)($conf['sitemap']['mod'] ?? '');
            $mod = ($modules_raw === '') ? ['0'] : explode(',', $modules_raw);
            for ($i = 0; $i < count($mod); $i++) {
                if ($mod[$i] == 'account' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT user_id, user_name, user_lastvisit FROM ".PREFIX_DB."_users");
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'content' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT id, title, time FROM ".PREFIX_DB."_content WHERE time <= NOW()");
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'faq' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT fid, catid, title, time FROM ".PREFIX_DB."_faq WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'files' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT lid, cid, title, date FROM ".PREFIX_DB."_files WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'forum' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT id, catid, title, time FROM ".PREFIX_DB."_forum WHERE pid = '0' AND time <= NOW() AND status > '1'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'jokes' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT jokeid, date, title, cat FROM ".PREFIX_DB."_jokes WHERE date <= NOW() AND status != '0'");
                    while (list($id, $time, $title, $cat) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'links' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT lid, cid, title, date FROM ".PREFIX_DB."_links WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'media' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT id, cid, title, subtitle, date FROM ".PREFIX_DB."_media WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $subtitle, $time) = $db->getSqlRow($result)) {
                        $title = ($subtitle) ? $title.' - '.$subtitle : $title;
                        $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                    }
                } elseif ($mod[$i] == 'news' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT sid, catid, title, time FROM ".PREFIX_DB."_news WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'pages' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT pid, catid, title, time FROM ".PREFIX_DB."_pages WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'shop' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT id, cid, time, title FROM ".PREFIX_DB."_products WHERE time <= NOW() AND active != '0'");
                    while (list($id, $cat, $time, $title) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'voting' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery("SELECT id, title, date FROM ".PREFIX_DB."_voting WHERE modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')");
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif (is_active($mod[$i], '0')) {
                    $info[$mod[$i]][] = ['', '', '', '', $mod[$i]];
                }
            }
            $map_h = $map_m = $map_c = $map_p = '';
            if (count($info) > 0) {
                foreach ($info as $key => $val) {
                    if ($conf['sitemap']['gen_m']) {
                        $map_m .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$key.'</loc>';
                        $map_m .= $conf['sitemap']['dat_m'] ? '<lastmod>'.$date.'</lastmod>' : '';
                        $map_m .= $conf['sitemap']['fr_m'] ? '<changefreq>'.$conf['sitemap']['fr_m'].'</changefreq>' : '';
                        $map_m .= $conf['sitemap']['pr_m'] ? '<priority>'.$conf['sitemap']['pr_m'].'</priority>' : '';
                        $map_m .= '</url>'."\n";
                    }
                    foreach ($info[$key] as $key2 => $val2) {
                        if ($conf['sitemap']['gen_p'] && $info[$key][$key2][0]) {
                            $map_p .= '<url><loc>'.$conf['homeurl']."/index.php?name=".$info[$key][$key2][4]."&amp;op=view&amp;id=".$info[$key][$key2][0].'</loc>';
                            $map_p .= $conf['sitemap']['dat_p'] ? '<lastmod>'.format_time($info[$key][$key2][3], 'Y-m-d').'</lastmod>' : '';
                            $map_p .= $conf['sitemap']['fr_p'] ? '<changefreq>'.$conf['sitemap']['fr_p'].'</changefreq>' : '';
                            $map_p .= $conf['sitemap']['pr_p'] ? '<priority>'.$conf['sitemap']['pr_p'].'</priority>' : '';
                            $map_p .= '</url>'."\n";
                        }
                        $htm[$key][$info[$key][$key2][1]][] = [$info[$key][$key2][0],$info[$key][$key2][2]];
                    }
                    $result = $db->getSqlQuery("SELECT id, modul, title, parentid FROM ".PREFIX_DB."_categories WHERE modul = :mod", ['mod' => $key]);
                    while (list($cid, $cmodul, $title, $parentid) = $db->getSqlRow($result)) {
                        $cd[$cid] = [$cid, $parentid, $title, $cmodul];
                        if ($conf['sitemap']['gen_c']) {
                            $map_c .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$cmodul.'&amp;cat='.$cid.'</loc>';
                            $map_c .= $conf['sitemap']['dat_c'] ? '<lastmod>'.$date.'</lastmod>' : '';
                            $map_c .= $conf['sitemap']['fr_c'] ? '<changefreq>'.$conf['sitemap']['fr_c'].'</changefreq>' : '';
                            $map_c .= $conf['sitemap']['pr_c'] ? '<priority>'.$conf['sitemap']['pr_c'].'</priority>' : '';
                            $map_c .= '</url>'."\n";
                        }
                    }
                }
            }
            if ($conf['sitemap']['txt']) {
                $buffer = '<ol class="sl_list">';
                foreach ($htm as $key => $val) {
                    $buffer .= '<li><a href="index.php?name='.$key.'" title="'.deflmconst($key).'">'.deflmconst($key).'</a>';
                    if (count($htm[$key]) > 0) {
                        $cat = '';
                        foreach ($htm[$key] as $key2 => $val2) {
                            $cat .= (isset($cd[$key2][2])) ? '<li><a href="index.php?name='.$key.'&amp;cat='.$key2.'" title="'.$cd[$key2][2].'">'.$cd[$key2][2].'</a>' : '';
                            if (count($htm[$key][$key2]) > 0) {
                                $view = $pub = '';
                                foreach ($htm[$key][$key2] as $key3 => $val3) {
                                    $view .= $htm[$key][$key2][$key3][0] ? '<li><a href="index.php?name='.$key.'&amp;op=view&amp;id='.$htm[$key][$key2][$key3][0].'" title="'.$htm[$key][$key2][$key3][1].'">'.$htm[$key][$key2][$key3][1].'</a></li>' : '';
                                }
                                $pub .= $view ? '<ol class="sl_sublist_two">'.$view.'</ol>' : '';
                            }
                            $cat .= isset($cd[$key2][2]) ? $pub.'</li>' : '';
                        }
                        $buffer .= $cat ? '<ol class="sl_sublist">'.$cat.'</ol>' : $pub;
                    }
                    $buffer .= '</li>';
                }
                $buffer .= '</ol>';
                file_put_contents(SITEMAP_DIR.'/sitemap.txt', $buffer);
            }
            if ($conf['sitemap']['gen_h']) {
                $map_h = '<url><loc>'.$conf['homeurl'].'/index.php</loc>';
                $map_h .= ($conf['sitemap']['dat_h']) ? '<lastmod>'.$date.'</lastmod>' : '';
                $map_h .= ($conf['sitemap']['fr_h']) ? '<changefreq>'.$conf['sitemap']['fr_h'].'</changefreq>' : '';
                $map_h .= ($conf['sitemap']['pr_h']) ? '<priority>'.$conf['sitemap']['pr_h'].'</priority>' : '';
                $map_h .= '</url>'."\n";
            }
            $map = $map_h.$map_m.$map_c.$map_p;
            $array = explode("\n", $map);
            # Maximum number of links
            $max = 50000;
            # Maximum size in bytes
            $size = 10485760;
            if (count($array) > $max) {
                $i = 1;
                $links = '';
                foreach (array_chunk($array, $max, true) as $sitemap) {
                    $urls = '';
                    foreach ($sitemap as $val) $urls .= empty($val) ? '' : $val."\n";
                    $cont = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
                    $cont .= ($conf['sitemap']['xsl'] && file_exists(SITEMAP_DIR.'/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n" : '';
                    $cont .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$urls.'</urlset>';
                    if ($conf['rewrite']) {
                        $cont = str_replace($conf['homeurl'].'/', '', $cont);
                        $cont = preg_replace('#<loc>(.*?)</loc>#is','<loc>'.$conf['homeurl'].'/\\1</loc>', $cont);
                    }
                    $file = 'sitemap-'.$i.'.xml';
                    file_put_contents($file, $cont);
                    $i++;
                    if (strlen($cont) >= $size && zip_check() == 2 && file_exists($file)) {
                        zip_compress($file, $file);
                        $gz = $file.'.gz';
                        if (file_exists($gz)) {
                            unlink($file);
                            $file = $gz;
                        }
                    }
                    $links .= '<sitemap><loc>'.$conf['homeurl'].'/'.$file.'</loc><lastmod>'.$date.'</lastmod></sitemap>'."\n";
                }
                $set = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$links.'</sitemapindex>';
            } else {
                $set = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$map.'</urlset>';
            }
            $cont = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $cont .= ($conf['sitemap']['xsl'] && file_exists(SITEMAP_DIR.'/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n".$set : $set;
            if ($conf['rewrite']) {
                $cont = str_replace($conf['homeurl'].'/', '', $cont);
                $cont = preg_replace('#<loc>(.*?)</loc>#is', '<loc>'.$conf['homeurl'].'/\\1</loc>', $cont);
            }
            file_put_contents('sitemap.xml', $cont);
        }
    }
}

# Navigation tabs (compact, synchronized & sequential IDs)
function getNaviTabs(int $id = 0, string $pref = '', array $tabs = [], array $conts = []): string {
    $tabs = is_array($tabs) ? $tabs : [];
    $conts = is_array($conts) ? $conts : [];
    $cnt = 0;
    $pairs = array_filter(array_map(
        function($k, $t, $c) use (&$cnt) {
            if (!empty($t) && !empty($c)) {
                $p = ['id' => $cnt, 'tab' => $t, 'cont' => $c];
                $cnt++;
                return $p;
            }
            return null;
        },
        array_keys($tabs),
        $tabs,
        $conts
    ));
    $tlinks = implode('', array_map(fn($p) => '<li><a href="#'.$pref.'_'.$id.'_'.$p['id'].'">'.$p['tab'].'</a></li>', $pairs));
    $cdivs = implode('', array_map(fn($p) => '<div id="'.$pref.'_'.$id.'_'.$p['id'].'">'.$p['cont'].'</div>', $pairs));
    return '<div id="sl_tabs_'.$id.'"><ul>'.$tlinks.'</ul>'.$cdivs.'</div>';
}

# Transliteration
function getTranslit(string $st, string $lo = ''): string {
    $st = strtr($st, ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => 'yo', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ь' => '', 'ю' => 'yu', 'я' => 'ya', 'Ё' => 'Yo', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => '', 'Ь' => '', 'Ю' => 'Yu', 'Я' => 'Ya']);
    $st = empty($lo) ? $st : mb_strtolower($st);
    $st = preg_replace('#[^a-zA-Z0-9]#', '', $st);
    $st = trim($st);
    return $st;
}

# Social networks code
function getNetworks(): string {
 global $conf;
    if ($conf['users']['network_c']) {
        $url = urlencode($conf['homeurl'].'/index.php?name=account&op=network');
        $st = ['[url]' => $url];
        $cont = strtr($conf['users']['network_c'], $st);
    } else {
        $cont = '';
    }
    return $cont;
}

# Get captcha
function getCaptcha(int $id): string {
 global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $cont = '<script src="https://www.google.com/recaptcha/api.js?render='.$conf['capkey'].'"></script>
        <script>grecaptcha.ready(function() { grecaptcha.execute("'.$conf['capkey'].'", { action: "homepage" }) .then(function(token) { document.getElementById("recaptcha").value = token; }); });</script>';
        $cont .= '<input type="hidden" id="recaptcha" name="recaptcha">';
    } else {
        $cont = '';
    }
    return $cont;
}

# Hints and tips on the version, size, time, etc.
function getHint(mixed $val, int $typ = 0, int $mod = 0, int $flg = 0, int $cut = 0, int $usef = 0, string|int $cmp1 = 0, string|int $cmp2 = 0, string $tit = ''): string {
    $ok  = ($mod === 0 || $mod === 2);
    $grn = $ok ? 'sl_green sl_note' : 'sl_red sl_note';
    $red = $ok ? 'sl_red sl_note'   : 'sl_green sl_note';
    $r5  = $ok ? _RATE5 : _RATE1;
    $r1  = $ok ? _RATE1 : _RATE5;
    $acon = $usef ? files_size((string)$val) : $val;
    if ($cut > 0) $acon = cutstr((string)$acon, $cut);
    $info = !empty($tit) ? ' - '.$tit : '';
    switch ($typ) {
        case 1:
            return '<span title="'.htmlspecialchars($tit, ENT_QUOTES, 'UTF-8').'" class="sl_blue sl_note">'.$acon.'</span>';
        case 2:
            $on  = ($flg === 0) ? _ON  : $r5;
            $off = ($flg === 0) ? _OFF : $r1;
            if ($mod <= 1) return ($val == 0) ? '<span title="'.$on.'" class="'.$grn.'">'._ON.'</span>' : '<span title="'.$off.'" class="'.$red.'">'._OFF.'</span>';
            return ($val != 0)   ? '<span title="'.$on.'" class="'.$grn.'">'._ON.'</span>' : '<span title="'.$off.'" class="'.$red.'">'._OFF.'</span>';
        case 3:
            $eq  = (string)$cmp1 === (string)$cmp2;
            $cls = $eq ? $grn : $red;
            $ttl = ($eq ? $r5 : $r1).$info;
            return '<span title="'.$ttl.'" class="'.$cls.'">'.$acon.'</span>';
        default:
            preg_match('#[\d]+#', (string)$val, $m);
            $num = isset($m[0]) && is_numeric($m[0]);
            if ($num) {
                if ($val <= $cmp1 && $cmp1) {
                    $cls = $grn;
                    $ttl = $r5.$info;
                } elseif ($val <= $cmp2 && $cmp2) {
                    $cls = 'sl_orange sl_note';
                    $ttl = _RATE3.$info;
                } else {
                    $cls = $red;
                    $ttl = $r1.$info;
                }
                return '<span title="'.$ttl.'" class="'.$cls.'">'.$acon.'</span>';
            }
            return '<span title="'.htmlspecialchars($tit, ENT_QUOTES, 'UTF-8').'" class="sl_blue sl_note">'.$acon.'</span>';
    }
}

# Convert image to base64
function getImgEncode(array $img): string {
    if (file_exists($img[1]) && filesize($img[1]) <= 10240) {
        $type = pathinfo($img[1], PATHINFO_EXTENSION);
        static $argc, $cach;
        if ($argc != $img[1] || !isset($cach)) {
            $argc = $img[1];
            $cach = base64_encode(file_get_contents($argc));
        }
        $cont = 'url(data:image/'.$type.';base64,'.$cach.')';
    } else {
        $cont = 'url('.$img[1].')';
    }
    return $cont;
}

# Compress CSS
function getCompressCss(string $css): string {
    # Remove multiline comment
    $css = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $css);
    # Remove tabs, spaces, newlines
    $css = str_replace(["\n", "\r", "\t"], ' ', $css);
    # Remove extra spaces
    $css = preg_replace('#\s+#', ' ', $css);
    # Remove spaces that can be removed
    $css = preg_replace('#\s?([\{\}\:\;\,])\s?#', "\\1", $css);
    return $css;
}

# Compress Code
function getCompressCode(string $code): string {
    # Remove multiline comment
    $code = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $code);
    # Remove tabs and extra spaces
    $code = str_replace(["\t", '  ', '   ', '    '], ' ', $code);
    # Remove other spaces before/after )
    $code = preg_replace(['#( )+\]#', '#\)( )+#'], ')', $code);
    # Remove spaces that can be removed
    $code = preg_replace('#\s?([\{\=-])\s?#', "\\1", $code);
    return $code;
}

# Compress HTML
function getCompressHtml(string $html): string {
    preg_match_all('#(<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>)#si', $html, $pre);
    $html = preg_replace('#<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>#si', '%pre%', $html);
    $html = preg_replace('#<!--[^\[].+-->#', '', $html);
    $html = preg_replace('#[\r\n\t]+#', ' ', $html);
    $html = preg_replace('#>[\s]+<#', '><', $html);
    $html = preg_replace('#[\s]+#', ' ', $html);
    if (!empty($pre[0])) {
        foreach ($pre[0] as $tag) {
            $html = preg_replace('#%pre%#', $tag, $html, 1);
        }
    }
    return $html;
}

# Voting view
function getVoting(int $id = 0, string $votid = ''): string {
 global $db, $afile, $user, $locale, $conf;
    if ($conf['multilingual'] == 1) {
        $querylang = "(language = :locale OR language = '') AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $qlang_params = ['locale' => $locale];
    } else {
        $querylang = "date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $qlang_params = [];
    }
    if (!$id)    $id    = getVar('get', 'id', 'num', 0);
    if (!$votid) $votid = analyze(getVar('post', 'votid', 'text', 'voting')) ?: 'voting';
    $result = $db->getSqlQuery("SELECT modul, title, questions, answer, enddate, multi, comments, acomm, typ, status FROM ".PREFIX_DB."_voting WHERE id = :id AND ".$querylang, array_merge(['id' => $id], $qlang_params));
    if ($db->getSqlRowCount($result) > 0) {
        $ip = getIp();
        $past = time() - intval($conf['voting']['voting_t']);
        $cmod = substr("voting", 0, 2)."-".$id;
        $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : "";
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->getSqlQuery("DELETE FROM ".PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
        list($num) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(id) FROM ".PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND host = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
        list($modul, $title, $questions, $answer, $enddate, $multi, $comments, $acomm, $typ, $status) = $db->getSqlRow($result);
        $rate = ($cookies == $id || $num > 0 || strtotime($enddate) <= time()) ? 1 : 0;
        if ($typ || !$typ && !$rate) {
            $questions = explode("|", $questions);
            $answer = explode("|", $answer);
            $vote = array_sum($answer);
            $form = (!$rate) ? "<form name=\"voting\" id=\"form".$votid."\" method=\"post\">" : "";
            $cont = setTemplateBasic("voting-open", ['{%form%}' => $form, '{%title%}' => $title]);
            $pn = 0;
            for ($i = 0; $i < count($questions); $i++) {
                $pn++;
                if ($pn > 5) $pn = 1;
                $n = $i + 1;
                if ($vote > 0) {
                    $proc = 100 * $answer[$i] / $vote;
                    $procent = number_format($proc, 2);
                } else {
                    $procent = "0.00";
                }
                if (!$rate) {
                    $itype = ($multi) ? "checkbox" : "radio";
                    $cont .= setTemplateBasic("voting-post", ['{%id%}' => $id, '{%n%}' => $n, '{%itype%}' => $itype, '{%name%}' => 'questions[]', '{%text%}' => $questions[$i]]);
                } else {
                    $cont .= setTemplateBasic("voting-view", ['{%text%}' => $questions[$i], '{%text_safe%}' => text_filter($questions[$i]), '{%n%}' => $n, '{%pn%}' => $pn, '{%percent%}' => $procent, '{%votes_label%}' => _VOTES, '{%votes%}' => $answer[$i]]);
                }
            }
            list($vnum) = $db->getSqlRow($db->getSqlQuery("SELECT COUNT(id) FROM ".PREFIX_DB."_voting WHERE ".$querylang, $qlang_params));
            $admin = (is_moder("voting") && $votid == "voting") ? add_menu("<a href=\"".$afile.".php?name=voting&amp;op=add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$afile.".php?name=voting&amp;op=delete&amp;id=".$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$title."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>") : "";
            $post = (!$rate) ? "<span OnClick=\"AjaxLoad('POST', '1', '".$votid."', 'go=1&amp;op=avoting_save&amp;id=".$id."&amp;votid=".$votid."', { 'questions%5B%5D':'"._SEROR1."' }); return false;\" title=\""._VOTE."\" class=\"sl_but_blue\">"._VOTE."</span>" : "";
            $polls = ($vnum > 1) ? "<a href=\"index.php?name=voting\" title=\""._POLLS."\" class=\"sl_but\">"._POLLS."</a>" : "";
            $votes = (!$modul && $votid != "voting") ? "<a href=\"index.php?name=voting&amp;op=view&amp;id=".$id."\" title=\""._VOTES."\" class=\"sl_votes\">"._VOTES.": ".$vote."</a>" : "<span class=\"sl_votes\">"._VOTES.": ".$vote."</span>";
            $comm = (!$modul && $acomm) ? "<a href=\"index.php?name=voting&amp;op=view&amp;id=".$id."#".$id."\" title=\""._COMMENTS."\" class=\"sl_coms\">"._COMMENTS.": ".$comments."</a>" : "";
            $formend = (!$rate) ? "</form>" : "";
            $cont .= setTemplateBasic("voting-close", ['{%admin%}' => $admin, '{%post%}' => $post, '{%polls%}' => $polls, '{%votes%}' => $votes, '{%comm%}' => $comm, '{%formend%}' => $formend]);
        } else {
            $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _VCLINFO]);
        }
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    return $cont;
}

# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad(int $tcache = 2): array {
    static $cache = ['time' => 0, 'cpu' => _NO_INFO, 'info' => _NO_INFO];
    if (time() - $cache['time'] < $tcache) return [$cache['cpu'], $cache['info']];
    $percent = null;
    if (stristr(PHP_OS, 'WIN')) {
        $out = [];
        $cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
        if (function_exists('exec')) exec($cmd, $out);
        if (!empty($out)) {
            $val = str_replace(',', '.', trim($out[0]));
            if (is_numeric($val)) $percent = (float)$val;
        }
        if ($percent === null) {
            $out = [];
            $cmd = 'wmic cpu get loadpercentage /all';
            if (function_exists('exec')) exec($cmd, $out);
            if ($out) {
                foreach ($out as $line) {
                    if ($line && preg_match('#^[0-9]+$#', $line)) {
                        $percent = (float)$line;
                        break;
                    }
                }
            }
        }
    } else {
        if (function_exists('sys_getloadavg')) {
            $tmp = sys_getloadavg();
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        if (!isset($raw) && file_exists('/proc/loadavg')) {
            $tmp = explode(' ', file_get_contents('/proc/loadavg'));
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $nproc = 0;
        if (file_exists('/proc/cpuinfo')) {
            $info = file_get_contents('/proc/cpuinfo');
            if ($info !== false) {
                preg_match_all('/^processor\s*:/m', $info, $matches);
                if (!empty($matches[0])) $nproc = count($matches[0]);
            }
        }
        if ($nproc <= 0) $nproc = 1;
        if (isset($raw) && is_numeric($raw)) $percent = ($raw / $nproc) * 10.0;
    }
    if (is_numeric($percent)) {
        $cpu = round((float)$percent, 2);
        if ($cpu < 0) $cpu = 0.0;
        if ($cpu > 100) $cpu = 100.0;
        $info = _PLOAD1;
    } else {
        $cpu = $info = _NO_INFO;
    }
    $cache = ['time' => time(), 'cpu' => $cpu, 'info' => $info];
    return [$cpu, $info];
}

# Variable analyzer
function getVariables(): string {
 global $db, $conf;
    $cont = '';
    $cvar = explode(',', $conf['variables']);
    if ($cvar[1]) {
        list($cpu, $info) = getCpuLoad(4);
        $cpucont = _PLOAD.': '.getHint($cpu, 0, 0, 0, 0, 0, 50, 80, $info).' % <progress max="100" value="'.$cpu.'">'.$cpu.' %</progress>';
        $memcont = _MEML.': '.getHint(memory_get_usage(), 0, 0, 0, 0, 1, 10485760, 20971520, 0).' <progress max="'.(str_replace('M', '', ini_get('memory_limit')) * 1024 * 1024).'" value="'.memory_get_usage().'">'.files_size(memory_get_usage()).'</progress>';
        $cont .= '<fieldset class="sl_sys_var"><legend style="color: darkgreen;">'._SYSTEM_INFO.'</legend>'.$cpucont.'<br>'.$memcont.'<br>'.getTimeLoads().'</fieldset>';
    }
    if ($cvar[2] && $_POST) $cont .= '<fieldset class="sl_sys_var"><legend style="color: green;">'._AVARIABLES.': POST</legend>'.htmlspecialchars(print_r($_POST, true)).'</fieldset>';
    if ($cvar[3] && $_GET) $cont .= '<fieldset class="sl_sys_var"><legend style="color: blue;">'._AVARIABLES.': GET</legend>'.htmlspecialchars(print_r($_GET, true)).'</fieldset>';
    if ($cvar[4] && $_COOKIE) $cont .= '<fieldset class="sl_sys_var"><legend style="color: orangered;">'._AVARIABLES.': COOKIE</legend>'.print_r($_COOKIE, true).'</fieldset>';
    if ($cvar[5] && $_FILES) $cont .= '<fieldset class="sl_sys_var"><legend style="color: purple;">'._AVARIABLES.': FILES</legend>'.print_r($_FILES, true).'</fieldset>';
    if ($cvar[6] && $_SESSION) $cont .= '<fieldset class="sl_sys_var"><legend style="color: fuchsia;">'._AVARIABLES.': SESSION</legend>'.print_r($_SESSION, true).'</fieldset>';
    if ($cvar[7] && $_SERVER) $cont .= '<fieldset class="sl_sys_var"><legend style="color: red;">'._AVARIABLES.': SERVER</legend>'.print_r($_SERVER, true).'</fieldset>';
    if ($cvar[8]) $cont .= '<fieldset class="sl_sys_var"><legend style="color: green;">'._AQUERY_DB.': MySQL</legend>'.$db->qtime.'</fieldset>';
    return $cont;
}

# Number of user news
function getUserNews(int $num): int {
 global $user, $conf;
    $unum = $user[3] ?? 0;
    $num = (!empty($unum) && $unum <= $num && $conf['users']['news'] == 1) ? intval($unum) : intval($num);
    return $num;
}

# Random password generation
function getPass(int $m): string {
    $m = intval($m);
    $pass = '';
    for ($i = 0; $i < $m; $i++) {
        $te = mt_rand(48, 122);
        if (($te > 57 && $te < 65) || ($te > 90 && $te < 97)) $te = $te - 9;
        $pass .= chr($te);
    }
    return $pass;
}

# Defining the server connection protocol
function getProtocol(): string {
    if ($_SERVER['SERVER_PORT'] == 443) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
        $proto = 'https';
    } elseif (strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, 5)) == 'https') {
        $proto = 'https';
    } else {
        $proto = 'http';
    }
    return $proto;
}

# Get the image from the text
function getImgText(string $text, string $type = '', bool $check = true): string|false {
 global $conf;
    if (preg_match('#\[attach=(.*?)\s(.*?)\]#i', $text, $match)) {
        $fname = basename(trim($match[1]));
        $img = (!$type) ? 'uploads/'.$conf['name'].'/thumb/'.$fname : 'uploads/'.$conf['name'].'/'.$fname;
    } elseif (preg_match('#\[img=[a-zA-Z]+\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } elseif (preg_match('#\[img\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } else {
        $img = '';
    }
    $img = empty($img) ? false : ($check ? (file_exists($img) ? $img : false) : $img);
    return $img;
}

# Format SEO url
function getSeoUrl(array $params): string {
 global $conf;
    $sep   = $conf['sep'] ?? '-';
    $tsep  = $conf['tsep'] ?? '-';
    $slugs = ['title', 'ctitle'];
    $segments = [];
    $query = [];
    foreach ($params as $key => $val) {
        if (in_array($key, $slugs, true)) continue;
        $segments[] = $val;
        $query[] = $key.'='.$val;
    }
    if ($conf['rewrite'] ?? false) {
        foreach ($slugs as $key) {
            if (!empty($conf[$key]) && !empty($params[$key])) {
                $segments[] = filterSlug($params[$key], $tsep);
            }
        }
        return implode($sep, $segments);
    }
    return 'index.php?'.implode('&amp;', $query);
}

function filterSlug(string $text, string $sep = '-'): string {
    $text = trim($text);
    static $rus = [
        'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'G',  'Д' => 'D',  'Е' => 'E',  'Ё' => 'E',  'Ж' => 'Zh',
        'З' => 'Z',  'И' => 'I',  'Й' => 'I',  'К' => 'K',  'Л' => 'L',  'М' => 'M',  'Н' => 'N',  'О' => 'O',
        'П' => 'P',  'Р' => 'R',  'С' => 'S',  'Т' => 'T',  'У' => 'U',  'Ф' => 'F',  'Х' => 'Kh', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        'Ъ' => '',   'Ь' => '',
        'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',  'е' => 'e',  'ё' => 'e',  'ж' => 'zh',
        'з' => 'z',  'и' => 'i',  'й' => 'i',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',
        'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',  'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'ъ' => '',   'ь' => '',
    ];
    $text = strtr($text, $rus);
    $text = preg_replace('~[^a-zA-Z0-9]+~', $sep, $text);
    $text = trim($text, $sep);
    return strtolower($text);
}

# Format theme
function getTheme(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
 global $user, $conf;
    if (defined('ADMIN_FILE')) return $cached = 'admin';
    $default = $conf['theme'] ?? 'default';
    if (!is_user()) return $cached = $default;
    $utheme = $user[5] ?? '';
    if ($utheme !== '' && is_dir(BASE_DIR.'/templates/'.$utheme)) return $cached = $utheme;
    return $cached = $default;
}

# Format theme file
function getThemeFile(string $name): string|false {
 global $home, $conf, $op;
    static $cache = [];
    static $files = null;
    static $dir = null;
    if ($files === null) {
        $dir = BASE_DIR.'/templates/'.getTheme().'/';
        $files = array_flip(scandir($dir, SCANDIR_SORT_NONE) ?: []);
    }
    $tpl = $conf['template'] ?? '';
    $mod = $conf['name'] ?? '';
    $opv = $op ?? '';
    $cat = getVar('get', 'cat', 'num', 0);
    $key = $name.'|'.(int)$home.'|'.$tpl.'|'.$mod.'|'.$opv.'|'.$cat;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $candidates = [];
    if ($home) {
        $candidates[] = $name.'-home';
    } elseif ($tpl !== '') {
        $candidates[] = $name.'-'.$tpl;
    } elseif ($mod !== '' && $opv !== '') {
        $candidates[] = $name.'-'.$mod.'-'.$opv;
        $candidates[] = $name.'-'.$mod;
    } elseif ($mod !== '' && $cat > 0) {
        $candidates[] = $name.'-'.$mod.'-cat-'.$cat;
        $candidates[] = $name.'-'.$mod;
    } elseif ($mod !== '') {
        $candidates[] = $name.'-'.$mod;
    }
    $candidates[] = $name;
    foreach ($candidates as $fname) {
        $file = $fname.'.html';
        if (isset($files[$file])) return $cache[$key] = $dir.$file;
    }
    return $cache[$key] = false;
}

# Get theme load
function getThemeLoad(string $tpl): string {
    $path = getThemeFile($tpl);
    if (!$path) return '';
    static $cache = [];
    if (array_key_exists($path, $cache)) return $cache[$path];
    $raw = file_get_contents($path);
    return $cache[$path] = $raw !== false ? $raw : '';
}

# Determining the load time
function getTimeLoads(): string {
 global $db, $sgtime;
    $ttime = sprintf('%.3f', microtime(true) - $sgtime);
    $qnums = $db->qnum;
    $sqltime = sprintf('%.3f', $db->sqltime);
    $cont = _GENERATION.': '.$ttime.' '._SEC.'. '._AND.' '.$qnums.' '._GENERATION_DB.' '.$sqltime.' '._SEC.'.';
    return $cont;
}
