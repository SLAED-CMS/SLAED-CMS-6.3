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

### Config bootstrap functions

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

### The beginning of new functions

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

    // ========================================
    // GZIP / BZIP2 COMPRESSION
    // ========================================

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

# Is number positive integer
function isInt(mixed $num): bool {
    $inum = (int)$num;
    return ($inum == $num && is_int($inum) && $num > 0) ? true : false;
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
        $result = $db->sql_query("SELECT id, title, description, img, parentid, auth_view, auth_read FROM ".PREFIX_DB."_categories ".$where." ORDER BY ordern, title", $params);
        while (list($cid, $title, $description, $img, $parentid, $auth_view, $auth_read) = $db->sql_fetchrow($result)) {
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
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(fid) FROM ".PREFIX_DB."_faq WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INFA;
            } elseif ($mod == 'files') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(lid) FROM ".PREFIX_DB."_files WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INF;
            } elseif ($mod == 'help') {
                $uid = intval($user[0]);
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(sid) FROM ".PREFIX_DB."_help WHERE catid IN (".$cin.") AND time <= NOW() AND pid = '0' AND uid = :uid", array_merge($pm, ['uid' => $uid])));
                $in = _INH;
            } elseif ($mod == 'jokes') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(jokeid) FROM ".PREFIX_DB."_jokes WHERE cat IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INJ;
            } elseif ($mod == 'links') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(lid) FROM ".PREFIX_DB."_links WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INL;
            } elseif ($mod == 'media') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_media WHERE cid IN (".$cin.") AND date <= NOW() AND status != '0'", $pm));
                $in = _INM;
            } elseif ($mod == 'news') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(sid) FROM ".PREFIX_DB."_news WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INN;
            } elseif ($mod == 'pages') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(pid) FROM ".PREFIX_DB."_pages WHERE catid IN (".$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INP;
            } elseif ($mod == 'shop') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_products WHERE cid IN (".$cin.") AND time <= NOW() AND active != '0'", $pm));
                $in = _INS;
            }
            return setTemplateBasic('categories', ['{%categories%}' => _CATEGORIES, '{%content%}' => $cont, '{%total%}' => _ALLIN, '{%pages%}' => $pnum, '{%in%}' => $in, '{%cat%}' => $cnum, '{%category%}' => _ALLINC, '{%mod%}' => $mod]);
        }
    }
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
        $res = $db->sql_query('SELECT id, auth_read FROM '.PREFIX_DB.'_categories '.$lng_where.' ORDER BY id', $lng_params);
        $catid = [];
        while (list($cid, $auth) = $db->sql_fetchrow($res)) {
            if (is_acess($auth)) $catid[] = (int)$cid;
        }
        $where = (!empty($catid)) ? ' WHERE '.$catfld.' IN ('.implode(', ',$catid).') AND '.$where : ' WHERE '.$where;
    } else {
        $where = $where ? ' WHERE '.$where : '';
    }
    $sql = 'SELECT COUNT('.$cntfld.') FROM '.PREFIX_DB.$tbl.$where;
    list($cnt) = $db->sql_fetchrow($db->sql_query($sql,$params));
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
    .'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'return '.$exp($data).';'.PHP_EOL;
    file_put_contents($fp, $cnt, LOCK_EX);
}

# DELETE OLD
function doConfig(string $fp, string $name, array $array, array|string $actual = '', string $type = ''): void {
    if (is_array($array) && $name) {
        if (is_array($actual)) $array += $actual;
        ksort($array);
        array_walk($array, function (&$v) { $v = is_bool($v) ? strval(intval($v)) : strval($v); });
        $cons = empty($type) ? 'FUNC_FILE' : 'ADMIN_FILE';
        $cont = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL.'if (!defined(\''.$cons.'\')) die(\'Illegal file access\');'.PHP_EOL.PHP_EOL.'$'.$name.' = '.var_export($array, true).';';
        file_put_contents($fp, $cont, LOCK_EX);
    }
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
                    $result = $db->sql_query("SELECT user_id, user_name, user_lastvisit FROM ".PREFIX_DB."_users");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'content' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, title, time FROM ".PREFIX_DB."_content WHERE time <= NOW()");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'faq' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT fid, catid, title, time FROM ".PREFIX_DB."_faq WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'files' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT lid, cid, title, date FROM ".PREFIX_DB."_files WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'forum' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, catid, title, time FROM ".PREFIX_DB."_forum WHERE pid = '0' AND time <= NOW() AND status > '1'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'jokes' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT jokeid, date, title, cat FROM ".PREFIX_DB."_jokes WHERE date <= NOW() AND status != '0'");
                    while (list($id, $time, $title, $cat) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'links' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT lid, cid, title, date FROM ".PREFIX_DB."_links WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'media' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, cid, title, subtitle, date FROM ".PREFIX_DB."_media WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $subtitle, $time) = $db->sql_fetchrow($result)) {
                        $title = ($subtitle) ? $title.' - '.$subtitle : $title;
                        $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                    }
                } elseif ($mod[$i] == 'news' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT sid, catid, title, time FROM ".PREFIX_DB."_news WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'pages' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT pid, catid, title, time FROM ".PREFIX_DB."_pages WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'shop' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, cid, time, title FROM ".PREFIX_DB."_products WHERE time <= NOW() AND active != '0'");
                    while (list($id, $cat, $time, $title) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'voting' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, title, date FROM ".PREFIX_DB."_voting WHERE modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
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
                    $result = $db->sql_query("SELECT id, modul, title, parentid FROM ".PREFIX_DB."_categories WHERE modul = :mod", ['mod' => $key]);
                    while (list($cid, $cmodul, $title, $parentid) = $db->sql_fetchrow($result)) {
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
                file_put_contents('config/sitemap/sitemap.txt', $buffer);
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
                    $cont .= ($conf['sitemap']['xsl'] && file_exists('config/sitemap/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n" : '';
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
            $cont .= ($conf['sitemap']['xsl'] && file_exists('config/sitemap/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n".$set : $set;
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
    $st = strtr($st, [
        'а' => 'a',  'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',  'ж' => 'g',    'з' => 'z',    'и' => 'i',    'й' => 'y',
        'к' => 'k',  'л' => 'l',    'м' => 'm',    'н' => 'n',    'о' => 'o',
        'п' => 'p',  'р' => 'r',    'с' => 's',    'т' => 't',    'у' => 'u',
        'ф' => 'f',  'ы' => 'i',    'э' => 'e',
        'А' => 'A',  'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
        'Е' => 'E',  'Ж' => 'G',    'З' => 'Z',    'И' => 'I',    'Й' => 'Y',
        'К' => 'K',  'Л' => 'L',    'М' => 'M',    'Н' => 'N',    'О' => 'O',
        'П' => 'P',  'Р' => 'R',    'С' => 'S',    'Т' => 'T',    'У' => 'U',
        'Ф' => 'F',  'Ы' => 'I',    'Э' => 'E',
        'ё' => 'yo', 'х' => 'h',    'ц' => 'ts',   'ч' => 'ch',   'ш' => 'sh',
        'щ' => 'shch', 'ъ' => '',   'ь' => '',     'ю' => 'yu',   'я' => 'ya',
        'Ё' => 'Yo', 'Х' => 'H',    'Ц' => 'Ts',   'Ч' => 'Ch',   'Ш' => 'Sh',
        'Щ' => 'Shch', 'Ъ' => '',   'Ь' => '',     'Ю' => 'Yu',   'Я' => 'Ya',
    ]);
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
    $result = $db->sql_query("SELECT modul, title, questions, answer, enddate, multi, comments, acomm, typ, status FROM ".PREFIX_DB."_voting WHERE id = :id AND ".$querylang, array_merge(['id' => $id], $qlang_params));
    if ($db->sql_numrows($result) > 0) {
        $ip = getIp();
        $past = time() - intval($conf['voting']['voting_t']);
        $cmod = substr("voting", 0, 2)."-".$id;
        $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : "";
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->sql_query("DELETE FROM ".PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
        list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND host = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
        list($modul, $title, $questions, $answer, $enddate, $multi, $comments, $acomm, $typ, $status) = $db->sql_fetchrow($result);
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
            list($vnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_voting WHERE ".$querylang, $qlang_params));
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

# User news DELETE
function user_news(mixed $unum, int $mnum): int {
    global $conf;
    $num = (!empty($unum) && $unum <= $mnum && $conf['users']['news'] == 1) ? intval($unum) : intval($mnum);
    return $num;
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
        'Ð'=>'A','Ð‘'=>'B','Ð’'=>'V','Ð“'=>'G','Ð”'=>'D','Ð•'=>'E','Ð'=>'E','Ð–'=>'Zh',
        'Ð—'=>'Z','Ð˜'=>'I','Ð™'=>'I','Ðš'=>'K','Ð›'=>'L','Ðœ'=>'M','Ð'=>'N','Ðž'=>'O',
        'ÐŸ'=>'P','Ð '=>'R','Ð¡'=>'S','Ð¢'=>'T','Ð£'=>'U','Ð¤'=>'F','Ð¥'=>'Kh','Ð¦'=>'Ts',
        'Ð§'=>'Ch','Ð¨'=>'Sh','Ð©'=>'Shch','Ð«'=>'Y','Ð­'=>'E','Ð®'=>'Yu','Ð¯'=>'Ya',
        'Ðª'=>'','Ð¬'=>'',
        'Ð°'=>'a','Ð±'=>'b','Ð²'=>'v','Ð³'=>'g','Ð´'=>'d','Ðµ'=>'e','Ñ‘'=>'e','Ð¶'=>'zh',
        'Ð·'=>'z','Ð¸'=>'i','Ð¹'=>'i','Ðº'=>'k','Ð»'=>'l','Ð¼'=>'m','Ð½'=>'n','Ð¾'=>'o',
        'Ð¿'=>'p','Ñ€'=>'r','Ñ'=>'s','Ñ‚'=>'t','Ñƒ'=>'u','Ñ„'=>'f','Ñ…'=>'kh','Ñ†'=>'ts',
        'Ñ‡'=>'ch','Ñˆ'=>'sh','Ñ‰'=>'shch','Ñ‹'=>'y','Ñ'=>'e','ÑŽ'=>'yu','Ñ'=>'ya',
        'ÑŠ'=>'','ÑŒ'=>'',
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
function getThemeLoad(string $tpl): ?string {
    $path = getThemeFile($tpl);
    if (!$path) return null;
    static $cache = [];
    if (array_key_exists($path, $cache)) return $cache[$path];
    $raw = file_get_contents($path);
    return $cache[$path] = $raw !== false ? $raw : null;
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

### End of new features

# Format Time
function datetime(int $id, string $name, string $time, int $max, string $class): string {
    static $jscript;
    $time = ($time) ? substr($time, 0, $max) : (($id == 1) ? date('Y-m-d H:i') : date('Y-m-d'));
    $class = ($class) ? 'sl_field '.$class : 'sl_field';
    if ($id == 1) {
        $format = "dateFormat: 'yy-mm-dd', timeFormat: 'HH:mm'";
        $typ = "datetimepicker";
    } else {
        $format = "dateFormat: 'yy-mm-dd', yearRange: '".(date('Y') - 100).":".date('Y')."'";
        $typ = "datepicker";
    }
    if (!isset($jscript)) {
        $cont = "<script src=\"plugins/jquery/ui/jquery-ui-timepicker.js\"></script>"
        ."<script src=\"plugins/jquery/ui/langs/".substr(_LOCALE, 0, 2).".js\"></script>";
        $jscript = 1;
    } else {
        $cont = "";
    }
    $cont .= "<script>$(function() { $('#".$name."').".$typ."({ changeMonth: true, changeYear: true, ".$format."}, $.timepicker.regional['".substr(_LOCALE, 0, 2)."']); });</script>"
    ."<input type=\"text\" id=\"".$name."\" name=\"".$name."\" value=\"".$time."\" maxlength=\"".$max."\" class=\"".$class."\">";
    return $cont;
}

# Save date and time for Data Base
function save_datetime(int $id, string $name = ''): string {
    if ($name) {
        $date = getVar('post', $name, 'raw', '') ?: getVar('get', $name, 'raw', '');
        if ($id == 1) {
            $cont = (date("Y-m-d H:i", strtotime($date)) == $date) ? $date.":00" : date("Y-m-d H:i:s");
        } else {
            $cont = (date("Y-m-d", strtotime($date)) == $date) ? $date : date("Y-m-d");
        }
    } else {
        $cont = ($id == 1) ? date("Y-m-d H:i:s") : date("Y-m-d");
    }
    return $cont;
}

# Format Time filter
function format_time(string $time, string $string = ''): string {
    $string = ($string) ? $string : _DATESTRING;
    $cont = date($string, strtotime($time));
    return $cont;
}

# Size filter
function files_size(mixed $size): string {
    $name = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $cont = ($size) ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).' '.$name[$i] : intval($size).' Bytes';
    return $cont;
}

# Format new graphic
function new_graphic(string $time): string {
    $data = time() - strtotime($time);
    $img = "";
    if ($data < 86400) $img = "<span title=\""._NEWTODAY."\" class=\"sl_n_day\"></span>";
    if (($data > 86400) && ($data < 259200)) $img = "<span title=\""._NEWLAST3DAYS."\" class=\"sl_n_days\"></span>";
    if (($data > 259200) && ($data < 604800)) $img = "<span title=\""._NEWTHISWEEK."\" class=\"sl_n_week\"></span>";
    return $img;
}

# Format radio form
function radio_form(mixed $var, string $name, string $id = ''): string {
    if ($id == 1) {
        $sel1 = (!$var) ? "checked" : "";
        $sel2 = ($var) ? "checked" : "";
        $content = "<label><input type=\"radio\" name=\"".$name."\" value=\"0\" ".$sel1."> "._YES." </label><label><input type=\"radio\" name=\"".$name."\" value=\"1\" ".$sel2."> "._NO."</label>";
    } else {
        $sel1 = ($var || !$var) ? "checked" : "";
        $sel2 = ($var == "0") ? "checked" : "";
        $content = "<label><input type=\"radio\" name=\"".$name."\" value=\"1\" ".$sel1."> "._YES." </label><label><input type=\"radio\" name=\"".$name."\" value=\"0\" ".$sel2."> "._NO."</label>";
    }
    return $content;
}

# Get gender
function get_gender(string $name, int $typ, string $class): string {
    $gender = [_NO_INFO, _MAN, _WOMAN];
    $cont = "<select name=\"".$name."\" class=\"sl_field ".$class."\">";
    foreach ($gender as $key => $val) {
        $select = ($key == $typ) ? " selected" : "";
        $cont .= "<option value=\"".$key."\"".$select.">".$val."</option>";
    }
    $cont .= "</select>";
    return $cont;
}

# Format gender
function gender(int $gender): string {
    if ($gender == 2) {
        $gen = _WOMAN;
    } elseif ($gender == 1) {
        $gen = _MAN;
    } else {
        $gen = _NO_INFO;
    }
    return $gen;
}

# OLD DELETE
# Format search highlight
function search_color(string $sourse, string $word): string {
 global $conf;
    $word = var_filter(urldecode($word));
    if ($word) {
        if (strstr($word, " ")) {
            $warray = explode(" ", str_replace("  ", " ", $word));
        } else {
            $warray[] = $word;
        }
        preg_match_all("#<[^>]*>#", $sourse, $tags);
        array_unique($tags);
        $taglist = [];
        $k = 0;
        foreach($tags[0] as $i) {
            $k++;
            $taglist[$k] = $i;
            $sourse = str_replace($i, "<".$k.">", $sourse);
        }
        foreach($warray as $i) if (!is_numeric($i)) $sourse = preg_replace("#".$i."#iu", "<span class=\"sl_word\">$0</span>", $sourse);
        foreach($taglist as $k => $i) $sourse = str_replace("<".$k.">", $i, $sourse);
    }
    return $sourse;
}

# Replace break
function replace_break(string $text): ?string {
 global $admin, $conf;
    if ($text) {
        $flag = is_array($admin) ? ($admin[3] ?? '') : '';
        $editor = (int)substr($flag, 0, 1);
        $out = ((defined("ADMIN_FILE") && $editor == 1) || (!defined("ADMIN_FILE") && $conf['redaktor'] == 1)) ? preg_replace("#<br.*>#i", "", $text) : $text;
        return $out;
    }
}

# DELETE OR MODIFY
# User country information
function user_geo_ip(string $ip, int $id = 4): string {
 global $conf;
    if ((PHP_VERSION >= "5") && $conf['geo_ip'] && preg_match("#([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3})#", $ip)) {
        if ($id == 1) {
            $cont = $ip;
        } elseif ($id == 2) {
            $cont = $ip;
        } elseif ($id == 3) {
            $name = $ip;
            $img = str_replace(" ", "_", strtolower($name));
            $imgl = (file_exists(img_find("language/".$img.".png"))) ? img_find("language/".$img.".png") : (($img == "?") ? img_find("language/white.png") : img_find("language/white.png"));
            $cont = "<img src=\"".$imgl."\" alt=\"".$name."\" title=\"".$name."\" class=\"sl_flag\">";
        } elseif ($id == 4) {
            $name = $ip;
            $img = str_replace(" ", "_", strtolower($name));
            $imgl = (file_exists(img_find("language/".$img.".png"))) ? img_find("language/".$img.".png") : (($img == "?") ? img_find("language/white.png") : img_find("language/white.png"));
            $cont = "<img src=\"".$imgl."\" alt=\"".$name."\" title=\"".$name."\" class=\"sl_flag\"><a href=\"".$conf['ip_link'].$ip."\" target=\"_blank\" title=\""._IP.": ".$ip."\">".$ip."</a>";
        }
    } else {
        $cont = ($id == 4) ? "<a href=\"".$conf['ip_link'].$ip."\" target=\"_blank\" title=\""._IP.": ".$ip."\">".$ip."</a>" : "";
    }
    return $cont;
}

# User information for user
function user_sinfo(string $id = ''): ?string {
 global $db, $conf;
    if ($conf['session']) {
        $who_online = ""; $m = 0; $b = 0; $u = 0; $i = 0;
        $result = $db->sql_query("SELECT uname, time, host_addr, guest, module FROM ".PREFIX_DB."_session ORDER BY uname");
        while (list($uname, $time, $host, $guest, $module) = $db->sql_fetchrow($result)) {
            $time = time() - $time;
            $strip = cutstr($uname, 15);
            $module = deflmconst($module);
            $linkstrip = cutstr($module, 15);
            if ($guest == 2) {
                $who_online .= "<tr><td>".user_geo_ip($host, 3)."<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($uname)."\" title=\"".display_time($time)."\">".$strip."</a></td><td title=\"".$module."\" class=\"sl_right sl_note\">".$linkstrip."</td></tr>";
                $m++;
            } elseif ($guest == 1 && $conf['botsact']) {
                $who_online .= "<tr><td>".user_geo_ip($host, 3)."<span title=\"".display_time($time)."\" class=\"sl_note\">".$strip."</span></td><td title=\"".$module."\" class=\"sl_right sl_note\">".$linkstrip."</td></tr>";
                $b++;
            } else {
                $who_online .= "";
                $u++;
            }
            $i++;
        }
        $content = "<hr><table class=\"sl_table_block\"><tr><td>"._BMEM.":</td><td class=\"sl_right\">".$m."</td></tr>";
        if ($conf['botsact']) $content .= "<tr><td>"._BOTS.":</td><td class=\"sl_right\">".$b."</td></tr>";
        $content .= "<tr><td>"._BVIS.":</td><td class=\"sl_right\">".$u."</td></tr><tr><td>"._OVERALL.":</td><td class=\"sl_right\">".$i."</td></tr></table><hr><table class=\"sl_table_block\"><tr><td class=\"sl_center\"><a OnClick=\"AjaxLoad('GET', '0', 'sinfo', 'go=1&amp;op=user_sinfo', ''); return false;\" title=\""._UPDATE."\" class=\"sl_but_green\">"._UPDATE."</a>";
        $content .= ($who_online) ? "<a OnClick=\"HideShow('u-block', 'slide', 'up', 500);\" title=\""._READMORE."\" class=\"sl_but_blue\">"._READMORE."</a></td></tr></table><table id=\"u-block\" class=\"sl_table_block sl_none\">".$who_online."</table>" : "</td></tr></table>";
        if ($id) { return $content; } else { echo $content; }
    }
}

# User information for admin
function user_sainfo(string $id = ''): ?string {
 global $db, $conf;
    if ($conf['session'] && is_admin()) {
        $a = $b = $m = $u = $i = 0;
        $who_online = ["0" => "", "1" => "", "2" => "", "3" => ""];
        $content_who = "";
        $result = $db->sql_query("SELECT uname, time, host_addr, guest, module, url FROM ".PREFIX_DB."_session ORDER BY uname");
        while (list($uname, $time, $host, $guest, $module, $url) = $db->sql_fetchrow($result)) {
            $time = time() - $time;
            $namestrip = cutstr($uname, 15);
            $lstrip = cutstr($module, 15);
            $alink = htmlspecialchars(urldecode($url));
            $alstrip = cutstr($alink, 15);
            $guest = intval($guest);
            if ($guest == 3) {
                $title_who = "<tr><td>".user_geo_ip($host, 3)."<a href=\"".$conf['ip_link'].$host."\" title=\"".display_time($time)." - "._IP.": ".$host."\" target=\"_blank\">".$namestrip."</a></td><td class=\"sl_right\"><a href=\"".$alink."\" title=\"".$alink."\" target=\"_blank\">".$alstrip."</a></td></tr>";
                $a++;
            } elseif ($guest == 2) {
                if ($lstrip != "") {
                    $title_who = "<tr><td>".user_geo_ip($host, 3)."<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($uname)."\" title=\"".display_time($time)." - "._IP.": ".$host."\" target=\"_blank\">".$namestrip."</a></td><td class=\"sl_right\"><a href=\"".$alink."\" title=\"".$alink."\" target=\"_blank\">".$lstrip."</a></td></tr>";
                    $m++;
                } else {
                    $title_who = "<tr><td>".user_geo_ip($host, 3)."<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($uname)."\" title=\"".display_time($time)." - "._IP.": ".$host."\" target=\"_blank\">".$namestrip."</a></td><td class=\"sl_right\"><a href=\"".$alink."\" title=\"".$alink."\" target=\"_blank\">".$alstrip."</a></td></tr>";
                }
            } elseif ($guest == 1) {
                $title_who = "<tr><td>".user_geo_ip($host, 3)."<a href=\"".$conf['ip_link'].$host."\" title=\"".display_time($time)." - "._IP.": ".$host."\" target=\"_blank\">".$namestrip."</a></td><td class=\"sl_right\"><a href=\"".$alink."\" title=\"".$alink."\" target=\"_blank\">".$lstrip."</a></td></tr>";
                $b++;
            } else {
                $title_who = ($u < 250) ? "<tr><td>".user_geo_ip($host, 3)."<a href=\"".$conf['ip_link'].$host."\" title=\"".display_time($time)."\" target=\"_blank\">".$uname."</a></td><td class=\"sl_right\"><a href=\"".$alink."\" title=\"".$alink."\" target=\"_blank\">".$lstrip."</a></td></tr>" : "";
                $u++;
            }
            $who_online[$guest] .= $title_who;
            $i++;
        }
        $content_who .= (is_admin_god()) ? "<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('ad-block', 'slide', 'up', 500);\" title=\""._READMORE."\" class=\"sl_plus\">"._ADMINS.":</a></td><td class=\"sl_right\">".$a."</td></tr></table><table id=\"ad-block\" class=\"sl_table_block sl_none\">".$who_online[3]."</table>" : "";
        $content_who .= "<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('us-block', 'slide', 'up', 500);\" title=\""._READMORE."\" class=\"sl_plus\">"._BMEM.":</a></td><td class=\"sl_right\">".$m."</td></tr></table><table id=\"us-block\" class=\"sl_table_block sl_none\">".$who_online[2]."</table>"
        ."<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('bo-block', 'slide', 'up', 500);\" title=\""._READMORE."\" class=\"sl_plus\">"._BOTS.":</a></td><td class=\"sl_right\">".$b."</td></tr></table><table id=\"bo-block\" class=\"sl_table_block sl_none\">".$who_online[1]."</table>"
        ."<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('an-block', 'slide', 'up', 500);\" title=\""._READMORE."\" class=\"sl_plus\">"._BVIS.":</a></td><td class=\"sl_right\">".$u."</td></tr></table><table id=\"an-block\" class=\"sl_table_block sl_none\">".$who_online[0]."</table>"
        ."<table class=\"sl_table_block\"><tr><td>"._OVERALL.":</td><td class=\"sl_right\">".$i."</td></tr></table><hr><table class=\"sl_table_block\"><tr><td class=\"sl_center\"><a OnClick=\"AjaxLoad('GET', '0', 'sainfo', 'go=1&amp;op=user_sainfo', ''); return false;\" title=\""._UPDATE."\" class=\"sl_but_green\">"._UPDATE."</a></td></tr></table>";
        if ($id) { return $content_who; } else { echo $content_who; }
    }
}

# Format admin block
function adminblock(): ?string {
 global $db, $afile;
    if (is_admin()) {
        $cont = '<table class="sl_table_block"><tr><td><a href="'.$afile.'.php" title="'._ADMINMENU.'">'._ADMINMENU.'</a></td></tr>'
        .'<tr><td><a href="'.$afile.'.php?op=logout" title="'._LOGOUT.'">'._LOGOUT.'</a></td></tr></table>';
        if (is_admin_god()) {
            list($title, $content) = $db->sql_fetchrow($db->sql_query("SELECT title, content FROM ".PREFIX_DB."_blocks WHERE bkey = 'admin'"));
            $cont .= '<hr>'.$content;
        }
        $a_title = ($title) ? $title : _ADMINS;
        return setTemplateBlock('block-left', ['{%title%}' => $a_title, '{%content%}' => $cont, '{%id%}' => '7']).setTemplateBlock('block-left', ['{%title%}' => _WHO, '{%content%}' => '<div id="repsainfo">'.user_sainfo(1).'</div>', '{%id%}' => '8']);
    }
}

# Newsletter send
function updateNewsletter(): void {
 global $db, $conf;
    if ($conf['newsletter']) {
        $result = $db->sql_query("SELECT id, title, content, mails FROM ".PREFIX_DB."_newsletter WHERE mails != ''");
        if ($db->sql_numrows($result) > 0) {
            list($id, $title, $content, $mails) = $db->sql_fetchrow($result);
            $ncount = intval($conf['newslettercount']);
            $id = intval($id);
            $mails = explode(",", $mails);
            $outmail = array_slice($mails, 0, $ncount);
            $inmail = implode(",", array_slice($mails, $ncount));
            $db->sql_query("UPDATE ".PREFIX_DB."_newsletter SET mails = :mails, send = send + :cnt, endtime = NOW() WHERE id = :id", ['mails' => $inmail, 'cnt' => $ncount, 'id' => $id]);
            foreach ($outmail as $val) if ($val != "") mail_send($val, $conf['adminmail'], $title, bb_decode($content, "all"), 0, 3);
            if (!$inmail) {
                $cont = ['newsletter' => '0'];
                setConfigFile('global.php', $cont, $conf);
            }
        }
    }
}

# User info link
function user_info(string $name): string {
    global $conf;
    if ($name) {
        $link = ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || is_admin()) ? "<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($name)."\" title=\""._PERSONALINFO."\">".$name."</a>" : $name;
    } else {
        $link = "";
    }
    return $link;
}

# Show kasse
function show_kasse(string $info = ''): string {
 global $db, $conf;
    $shop = (isset($_COOKIE['shop'])) ? base64_decode($_COOKIE['shop']) : "";
    $info = (empty($info)) ? $shop : base64_decode($info);
    $cookies = (preg_match("#[^0-9,]#", $info)) ? "" : $info;
    if ($cookies) {
        $massiv = explode(",", $cookies);
        $ids = array_values(array_unique(array_map('intval', $massiv)));
        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
        if (!$ids) return "";
        $pp = [];
        $pm = [];
        foreach ($ids as $k => $pid) {
            $ph = 'id'.$k;
            $pp[] = ':'.$ph;
            $pm[$ph] = $pid;
        }
        $result = $db->sql_query("SELECT id, time, title, preis FROM ".PREFIX_DB."_products WHERE id IN (".implode(', ', $pp).")", $pm);
        $cont = "";
        $preistotal = 0;
        while (list($id, $time, $title, $preis) = $db->sql_fetchrow($result)) {
            $i = 0;
            foreach ($massiv as $val) {
                if ($val == $id) $i++;
            }
            $preis = $preis * $i;
            $preistotal += $preis;
            $ptitle = "<a href=\"index.php?name=shop&amp;op=view&amp;id=".$id."\" title=\"".$title."\">".$title."</a> ".new_graphic($time);
            $mtitle = ($i > 1) ? _PMINUS : _DELETE;
            $plus = "<a OnClick=\"AjaxLoad('GET', '0', 'kasse', 'go=2&amp;op=add_kasse&amp;id=".$id."', ''); return false;\" title=\""._PPLUS."\" class=\"sl_shop_plus\"></a>";
            $minus = "<a OnClick=\"AjaxLoad('GET', '0', 'kasse', 'go=2&amp;op=del_kasse&amp;id=".$id."', ''); return false;\" title=\"".$mtitle."\" class=\"sl_shop_minus\"></a>";
            $cont .= setTemplateBasic("kasse-basic", ['{%id%}' => $id, '{%title%}' => $ptitle, '{%qty%}' => $i, '{%price%}' => $preis." ".$conf['shop']['valute'], '{%plus%}' => $plus, '{%minus%}' => $minus]);
        }
        $cart = "<a href=\"index.php?name=shop&amp;op=kasse\" title=\""._SCACH."\" class=\"sl_shop_kasse\">"._SCACH."</a>";
        $total = "<span title=\""._PARTNERGES."\" class=\"sl_shop_total\">"._PARTNERGES.": ".$preistotal." ".$conf['shop']['valute']."</span>";
        return setTemplateBasic("kasse-open", ['{%title%}' => _PBASKET, '{%col_id%}' => _ID, '{%col_product%}' => _PRODUCT, '{%col_qty%}' => cutstr(_QUANTITY, 3, 1), '{%col_price%}' => _PREIS, '{%col_fn%}' => _FUNCTIONS]).$cont.setTemplateBasic("kasse-close", ['{%cart%}' => $cart, '{%total%}' => $total]);
    }
}

# Add kasse
function add_kasse(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $cookies = (preg_match("#[^0-9,]#", base64_decode($_COOKIE['shop']))) ? "" : base64_decode($_COOKIE['shop']);
    if ($id) {
        setcookie("shop", false);
        if ($cookies) {
            $info = base64_encode($cookies.",".$id);
            setcookie("shop", $info, time() + $conf['shop']['shop_t']);
        } else {
            $info = base64_encode($id);
            setcookie("shop", $info, time() + $conf['shop']['shop_t']);
        }
    }
    echo show_kasse($info);
}

# Delete kasse
function del_kasse(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $cookies = (preg_match("#[^0-9,]#", base64_decode($_COOKIE['shop']))) ? "" : base64_decode($_COOKIE['shop']);
    if ($id && $cookies) {
        $massiv = explode(",", $cookies);
        setcookie("shop", false);
        $i = 0;
        $a = 0;
        $b = 0;
        foreach ($massiv as $val) {
            if ($val == $id && $a == 0) {
                $i++;
                $a++;
                $val = "";
            } else {
                if ($b == 0) {
                    $info = $val;
                    $b++;
                } else {
                    $info .= ",".$val;
                }
            }
        }
        $info = base64_encode($info);
        setcookie("shop", $info, time() + $conf['shop']['shop_t']);
    }
    echo show_kasse($info);
}

# Format user warnings
function warnings(string $warnings): string {
    if ($warnings) {
        $warns = explode("|", $warnings);
        $cont = "<ol>";
        foreach ($warns as $val) $cont .= ($val != "") ? "<li>".$val."</li>" : "";
        $cont .= "</ol>";
    } else {
        $cont = _NO;
    }
    return $cont;
}

# Format ajax rating
function ajax_rating(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string {
    global $conf;
    if (intval($rat)) {
        $votnum = $rat;
        $votes = $rat;
    } else {
        $votnum = 0;
        $votes = 1;
    }
    $width = number_format($scor / $votes, 2) * 20;
    $result = substr($scor / $votes, 0, 4);
    if (intval($votes) && intval($scor)) {
        $title = _RATING.": ".$result."/".$votes." "._AVERAGESCORE.": ".$result;
        $nrate = "sl_rate-num sl_rate-is";
    } else {
        $title = _RATING.": 0/0 "._AVERAGESCORE.": 0";
        $nrate = "sl_rate-num";
    }
    if ($stl == 1) {
        $img = "<span class=\"sl_none\">".$result."</span><div class=\"sl_rate-like\"><p title=\""._RATE1."\" class=\"sl_rate-minus\"><p title=\""._RATE5."\" class=\"sl_rate-plus\"></div><span title=\"".$title."\" class=\"".$nrate."\">".$result."</span>";
        $imgr = "<span class=\"sl_none\">".$result."</span><div OnMouseOver=\"AjaxLoad('GET', '0', '".$id.$obj."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$obj."&amp;mod=".$mod."&amp;stl=1', ''); return false;\" class=\"sl_rate-like\"><p title=\""._RATE1."\" class=\"sl_rate-minus\"><p title=\""._RATE5."\" class=\"sl_rate-plus\"></div><span class=\"".$nrate."\" title=\"".$title."\">".$result."</span>";
        $crate = "sl_rate-like";
    } else {
        $img = "<span class=\"sl_none\">".$result."</span><ul title=\"".$title."\" class=\"sl_urating\"><li class=\"sl_crating\" style=\"width: ".$width."%;\"></li></ul><span title=\""._VOTES."\" class=\"".$nrate."\">".$votnum."</span>";
        $imgr = "<span class=\"sl_none\">".$result."</span><ul OnMouseOver=\"AjaxLoad('GET', '0', '".$id.$obj."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$obj."&amp;mod=".$mod."', ''); return false;\" title=\"".$title."\" class=\"sl_urating\"><li class=\"sl_crating\" style=\"width: ".$width."%;\"></li></ul><span title=\""._VOTES."\" class=\"".$nrate."\">".$votnum."</span>";
        $crate = "sl_rate";
    }
    if ($typ == 2) {
        $content = "<div class=\"".$crate."\">".$img."</div>";
    } else {
        $con = explode("|", $conf['ratings'][strtolower($mod)]);
        if (($con[1] && $id && $mod) || ($rat && $scor)) {
            $content = (($con[1] && $typ) || ($con[1] && !$con[2] && !$typ)) ? "<div id=\"rep".$id.$obj."\" class=\"".$crate."\">".$imgr."</div>" : "<div class=\"".$crate."\">".$img."</div>";
        } else {
            $content = "";
        }
    }
    return $content;
}

# Show editor files
function show_files(): void {
 global $conf, $user;
    $uploads_data = include('config/uploads.php');
    $conf['uploads'] = $uploads_data['uploads'] ?? [];
    $id   = analyze(getVar('get', 'id',   'text', '')) ?: 0;
    $dir  = strtolower(getVar('get', 'dir',  'text', ''));
    $cid = getVar('get', 'cid', 'num', 0);
    $con = explode("|", (string)($conf['uploads'][$dir] ?? ''));
    $connum = (isset($con[7]) && intval($con[7])) ? $con[7] : "50";
    $eallf = (is_moder()) ? intval($con[8] ?? 0) : intval($con[9] ?? 0);
    $file = text_filter(getVar('get', 'file', 'raw', ''));
    $num = ($cid) ? $cid : "1";
    $uname = (is_user()) ? intval($user[0]) : 0;
    $path = "uploads/".$dir."/";
    if (is_moder($dir) && $file && $dir) {
        if (!$cid) {
            unlink($path.$file);
        } else {
            zip_compress($path.$file, $path.$file);
        }
    }
    $dh = opendir($path);
    while ($entry = readdir($dh)) {
        if ($entry != "." && $entry != ".." && $entry != "index.html" && !is_dir($path.$entry)) $files[] = [filemtime($path.$entry), $entry];
    }
    closedir($dh);
    if ($files) {
        $a = 0;
        rsort($files);
        foreach ($files as $entry) {
            preg_match("#([a-zA-Z0-9]+)\-([a-zA-Z0-9]+)\-([0-9]+)\.([a-zA-Z0-9]+)#", $entry[1], $date);
            if (($uname == $date[3] && $date[2] && $date[1]) || is_moder($dir)) {
                $filesize = filesize($path.$entry[1]);
                list($imgwidth, $imgheight) = getimagesize($path.$entry[1]);
                $type = strtolower(substr(strrchr($entry[1], "."), 1));
                $ftype = ["png", "jpg", "jpeg", "gif", "bmp"];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = "<div OnClick=\"HideShow('sf-form-".$a."', 'fold', 'up', 500);\" class=\"sl_drop sl_preview_mini\" style=\"background-image: url(".$path.$entry[1].");\" title=\""._IMG."\"><span id=\"sf-form-".$a."\" class=\"sl_drop-form\"><img src=\"".$path.$entry[1]."\" alt=\""._IMG."\" title=\""._IMG."\"></span></div>";
                    $show = "<a OnClick=\"InsertCode('attach', '".$entry[1]."', '', '', '".$id."')\" title=\""._INSERT." ".$imgwidth." x ".$imgheight."\">"._INSERT."</a>||<a OnClick=\"InsertCode('img', '".$path.$entry[1]."', '', '', '".$id."')\" return false;\" title=\""._EIMG." ".$imgwidth." x ".$imgheight."\">"._EIMG."</a>";
                } else {
                    $img = "<div class=\"sl_preview_mini\" style=\"background-image: url(templates/".$conf['theme']."/images/categories/no.png);\" title=\""._NO."\"></div>";
                    $show = "<a OnClick=\"InsertCode('attach', '".$entry[1]."', '', '', '".$id."')\" title=\""._INSERT."\">"._INSERT."</a>";
                }
                if (is_moder($dir)) {
                    $show .= (zip_check()) ? "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id."&amp;dir=".$dir."&amp;cid=1&amp;file=".$entry[1]."', ''); return false;\" title=\""._ZIP."\">"._ZIP."</a>" : "";
                    $show .= "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id."&amp;dir=".$dir."&amp;cid=0&amp;file=".$entry[1]."', ''); return false;\" title=\""._ONDELETE."\">"._ONDELETE."</a>";
                }
                $contents[] = "<tr><td>".$img."</td><td>".$entry[1]."</td><td>".files_size($filesize)."</td><td>".add_menu($show)."</td></tr>";
                $a++;
            }
            if ($eallf && $a == $eallf) break;
        }
    }
    $numpages = ceil($a / $connum);
    $offset = ($num - 1) * $connum;
    $tnum = ($offset) ? $connum + $offset : $connum;
    $cont = "";
    for ($i = $offset; $i < $tnum; $i++) {
        if ($contents[$i] != "") $cont .= $contents[$i];
    }
    $contnum = ($a > $connum) ? num_ajax("pagenum", $a, $numpages, $connum, "", $num, "0", "1", "show_files", "f".$id, $id, "", $dir) : "";
    $content = ($cont) ? "<table class=\"sl_table_ajax\"><thead class=\"sl_table_ajax_head\"><tr><th>".cutstr(_IMG, 4, 1)."</th><th>"._FILE."</th><th>"._SIZE."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_ajax_body\">".$cont."</tbody></table>".$contnum : "";
    echo $content;
}

# Add downloads
function stream(string $url, string $name): void {
    header("Content-Type: application/force-download");
    header("Content-Range: bytes");
    header("Content-Length: ".filesize($url));
    header("Content-Disposition: attachment; filename=".$name);
    readfile($url);

    /* https://secure.php.net/manual/ru/function.readfile.php
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }*/
}

# Anti spam
function anti_spam(string $mail): string {
    preg_match("#^(.*?)(@)(.*?)$#", $mail, $info);
    $cont = "<script>\"mysi\".AddMail('".$info[1]."', '".$info[3]."');</script><noscript>".$info[1]."<!-- slaed --><span>&#64;</span><!-- slaed -->".$info[3]."</noscript>";
    return $cont;
}

# Format letter
function letter(string $mod): string {
 global $db, $user;
    if ($mod == "faq") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_faq WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "files") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_files WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "help") {
        $uid = intval($user[0]);
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_help WHERE time <= NOW() AND pid = '0' AND uid = :uid", ['uid' => $uid]);
    } elseif ($mod == "links") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_links WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "media") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_media WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "news") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_news WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "pages") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_pages WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "shop") {
        $result = $db->sql_query("SELECT title FROM ".PREFIX_DB."_products WHERE time <= NOW() AND active != '0'");
    } else {
        $result = "";
    }
    if ($result) {
        while(list($title) = $db->sql_fetchrow($result)) $letdb[] = ucfirst(mb_substr(trim($title), 0, 1, "utf-8"));
        $alpha = array_unique($letdb);
    } else {
        $alpha = [];
    }
    $cont = "";
    foreach(range(0, 9) as $num) $cont .= (in_array("$num", $alpha)) ? "<a href=\"index.php?name=".$mod."&amp;op=liste&amp;let=".$num."\" title=\"".$num."\"><span class=\"sl_letter\">".$num."</span></a>" : "<span class=\"sl_letter\">".$num."</span>";
    $cont .= "<br>";
    foreach (preg_split("//u", _ALPHABET, -1, PREG_SPLIT_NO_EMPTY) as $char) $cont .= (in_array($char, $alpha)) ? "<a href=\"index.php?name=".$mod."&amp;op=liste&amp;let=".urlencode($char)."\" title=\"".$char."\"><span class=\"sl_letter\">".$char."</span></a>" : "<span class=\"sl_letter\">".$char."</span>";
    if (substr(_LOCALE, 0, 2) != "fr") {
        $cont .= "<br>";
        foreach(range("A", "Z") as $eng) $cont .= (in_array($eng, $alpha)) ? "<a href=\"index.php?name=".$mod."&amp;op=liste&amp;let=".$eng."\" title=\"".$eng."\"><span class=\"sl_letter\">".$eng."</span></a>" : "<span class=\"sl_letter\">".$eng."</span>";
    }
    return $cont;
}

# Format admin menu
function add_menu(string $links): ?string {
    if ($links) {
        $links = explode("||", $links);
        $cont = "<nav class=\"sl_menu\"><ul><li><span class=\"sl_but_red\">"._EDITOR."</span><ul>";
        foreach ($links as $val) if ($val != "") $cont .= "<li>".$val."</li>";
        $cont .= "</ul></li></ul></nav>";
        return $cont;
    }
}

# Format title tips
function title_tip(mixed $data): string {
    $data = is_array($data) ? implode("<br>", $data) : $data;
    $tip = "<nav class=\"sl_tip\"><div>".$data."</div></nav>";
    return $tip;
}

# Admin status
function ad_status(mixed $link, mixed $id, string $typ = '', string $text = ''): string {
    if ($typ) {
        $cont = ($id) ? "<span title=\""._PROLD."\" class=\"sl_n_act\"></span>" : "<span title=\""._PROUTNEW."\" class=\"sl_n_deact\"></span>";
    } elseif ($link) {
        $deact = ($text) ? _DEACTIVATE.": ".$text : _DEACTIVATE;
        $act = ($text) ? _ACTIVATE.": ".$text : _ACTIVATE;
        $cont = ($id == 1) ? "<a href=\"".$link."\" title=\"".$deact."\">".$deact."</a>" : "<a href=\"".$link."\" title=\"".$act."\">".$act."</a>";
    } else {
        $cont = ($id == 1) ? "<span title=\""._ACT."\" class=\"sl_n_act\"></span>" : "<span title=\""._DEACT."\" class=\"sl_n_deact\"></span>";
    }
    return $cont;
}

# Add mailto
function mailto(string $mail): string {
 global $conf;
    return "<a href=\"mailto:".$mail."?subject=".$conf['sitename']."\" target=\"_blank\">".$mail."</a>";
}

# Add save button
function ad_save(string $name = '', string $val = '', string $op = '', string $noPreview = ''): string {
    $cont = "<select name=\"posttype\" class=\"sl_field\">";
    if (!$noPreview) $cont .= "<option value=\"preview\">"._PREVIEW."</option>";
    $cont .= "<option value=\"save\">"._SEND."</option>";
    $cont .= ($val) ? "<option value=\"delete\">"._DELETE."</option></select>" : "</select>";
    $cont .= ($name && $val) ? "<input type=\"hidden\" name=\"".$name."\" value=\"".$val."\">" : "";
    $cont .= "<input type=\"hidden\" name=\"op\" value=\"".$op."\">"
    ." <input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\">";
    return $cont;
}

# Find img
function img_find(string $img): string {
    static $base;
    if (!$base) $base = 'templates/'.getTheme().'/images/';
    return $base.$img;
}

# Format select RSS
function rss_select(): string {
 global $conf;
    require_once CONFIG_DIR.'/rss.php';
    $fieldc = explode("||", $conf['rss']['rss']);
    $url = getVar('post', 'url', 'url', '');
    $cont = "";
    foreach ($fieldc as $val) {
        if ($val != "") {
            preg_match("#(.*)\|(.*)\|(.*)#i", $val, $out);
            if ($out[1] != "0" && $out[2] != "0") {
                $sel = ($url == $out[2]) ? " selected" : "";
                $link = (!preg_match("#http\:\/\/#i", $out[2])) ? $conf['homeurl']."/".$out[2] : $out[2];
                $cont .= "<option value=\"".$link."\"".$sel.">".$out[1]."</option>";
            }
        }
    }
    return $cont;
}

# Read RSS
function rss_read(mixed $url, mixed $id): ?string {
    if ($url) {
        require_once CONFIG_DIR.'/rss.php';
        $url = trim(html_entity_decode(str_replace(["&#038;", "&amp;"], "&", $url), ENT_QUOTES, 'UTF-8'));
        $url = (!preg_match('#^https?://#i', $url)) ? 'http://'.$url : $url;
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 1,
                'user_agent' => 'SLAED RSS Reader',
            ],
        ]);
        set_error_handler(static function (): bool { return true; });
        $content = file_get_contents($url, false, $context);
        restore_error_handler();
        if ($content) {
            if (preg_match('#encoding=["\']([^"\']+)#i', $content, $val) && !empty($val[1])) {
                $encoding = strtolower($val[1]);
                if ($encoding != 'utf-8') {
                    $converted = iconv($val[1], 'utf-8//IGNORE', $content);
                    if ($converted !== false) $content = $converted;
                }
            }
            $title = parse_url($url, PHP_URL_HOST);
            if (!$title) $title = $url;
            preg_match_all("#<item>(.*)</item>#Uism", $content, $items, PREG_PATTERN_ORDER);
            if (!empty($items[1])) {
                $number = ($conf['rss']['max'] > count($items[1])) ? count($items[1]) : $conf['rss']['max'];
                $cont = "";
                for ($i = 0; $i < $number; $i++) {
                    preg_match("#<title>(.*)</title>#Uism", $items[1][$i], $rss_title);
                    preg_match("#<pubDate>(.*)</pubDate>#Uism", $items[1][$i], $rss_date);
                    preg_match("#<guid>(.*)</guid>(.*)#Uism", $items[1][$i], $rss_guid);
                    preg_match("#<description>(.*)</description>#Uism", $items[1][$i], $rss_desc);
                    $temp = $conf['rss']['temp'];
                    $rss_title = $rss_title[1] ?? '';
                    $rss_date = $rss_date[1] ?? '';
                    $rss_guid = $rss_guid[1] ?? '';
                    $rss_desc = $rss_desc[1] ?? '';
                    $rss_date = ($rss_date && strtotime($rss_date) !== false) ? date(_DATESTRING, strtotime($rss_date)) : '';
                    $temp = str_replace("[title]", $rss_title, $temp);
                    $temp = str_replace("[date]", $rss_date, $temp);
                    $temp = str_replace("[guid]", $rss_guid, $temp);
                    $temp = str_replace("[description]", text_filter(html_entity_decode(str_replace("]]>", "", $rss_desc))), $temp);
                    $cont .= $temp;
                }
                $cont = ($id) ? $cont : "<h2>"._RSS_FROM.": <a href=\"".htmlspecialchars($url)."\" target=\"_blank\" title=\""._RSS_FROM.": ".$title."\">".$title."</a></h2>".$cont;
            } else {
                $cont = ($id) ? "" : setTemplateWarning('warn', ['text' => _RSS_PROBLEM, 'url' => '', 'time' => 0, 'id' => 'warn']);
            }
        } else {
            $cont = ($id) ? "" : setTemplateWarning('warn', ['text' => _RSS_PROBLEM, 'url' => '', 'time' => 0, 'id' => 'warn']);
        }
        return $cont;
    }
}

# Load RSS
function rss_load(mixed $bid): void {
 global $db;
    $bid = intval($bid);
    list($title, $content, $url, $refresh, $otime) = $db->sql_fetchrow($db->sql_query("SELECT title, content, url, refresh, time FROM ".PREFIX_DB."_blocks WHERE bid = :bid", ['bid' => $bid]));
    $past = time() - $refresh;
    if ($otime < $past) {
        $btime = time();
        $content = rss_read($url, 1);
        $db->sql_query("UPDATE ".PREFIX_DB."_blocks SET content = :content, time = :time WHERE bid = :bid", ['content' => $content, 'time' => $btime, 'bid' => $bid]);
    }
    echo setTemplateBlock('', ['{%title%}' => $title, '{%content%}' => $content]);
}

# Preview
function preview(string $title = '', string $text1 = '', string $text2 = '', string $text3 = '', string $mod = ''): string {
    $fields  = $title ? "<b>".$title."</b>" : "";
    $fields1 = $text1 ? (($fields) ? "<br><br>".bb_decode($text1, $mod) : bb_decode($text1, $mod)) : "";
    $fields2 = $text2 ? "<br><br>".bb_decode($text2, $mod) : "";
    $fields3 = $text3 ? "<br><br>".fields_out(bb_decode($text3, $mod), $mod) : "";
    return setTemplateBasic('preview', ['{%title%}' => _PREVIEW, '{%fields%}' => $fields, '{%fields1%}' => $fields1, '{%fields2%}' => $fields2, '{%fields3%}' => $fields3]);
}

# Defined constant
function defconst(string $con): string {
    $out = (defined($con)) ? constant($con) : $con;
    return $out;
}

# Defined lang modul names constant
function deflmconst(string $con): string {
    $val = ['account' => _ACCOUNT, 'album' => _ALBUM, 'all' => _ALL, 'auto_links' => _A_LINKS, 'clients' => _CLIENTS, 'contact' => _FEEDBACK, 'content' => _CONTENT, 'faq' => _FAQ, 'files' => _FILES, 'forum' => _FORUM, 'gallery' => _ALBUM, 'help' => _HELP, 'info' => _INFO, 'radio' => _RADIO, 'jokes' => _JOKES, 'links' => _LINKS, 'main' => _MAIN, 'media' => _MEDIA, 'members' => _USERS, 'money' => _MONEY, 'news' => _NEWS, 'order' => _ORDER, 'pages' => _PAGES, 'recommend' => _RECOMMEND, 'rss_info' => _RSS, 'search' => _SEARCH, 'shop' => _SHOP, 'users' => _TOPUSERS, 'voting' => _VOTING, 'whois' => _WHOIS, 'sitemap' => _SITEMAP];
    return strtr($con, $val);
}

# Defined lang constant
function deflang(string $con): string {
    $val = ['en' => _ENGLISH, 'fr' => _FRENCH, 'de' => _GERMAN, 'pl' => _POLISH, 'ru' => _RUSSIAN, 'uk' => _UKRAINIAN];
    return strtr($con, $val);
}

# Fields in
function fields_in(mixed $fieldb, string $mod): string {
 global $conf;
    $mod = strtolower($mod);
    $style = (defined('ADMIN_FILE')) ? 'sl_field sl_form' : 'sl_field '.$conf['style'];
    $fieldc = $conf['fields'][$mod];
    $field = getVar('post', 'field', 'raw', '');
    if ($field !== '') {
        $fieldb = fields_save($field);
    }
    $fieldb = explode('|', $fieldb ?? '');
    $fieldc = explode('||', $fieldc);
    $i = 0;
    $fields = '';
    foreach ($fieldc as $val) {
        if ($val != '') {
            preg_match("#(.*)\|(.*)\|(.*)\|(.*)#i", $val, $out);
            if ($out[1] != "0") {
                $fieldin = (!empty($fieldb[$i])) ? $fieldb[$i] : $out[2];
                $requir = ($out[4] == 1) ? " required" : "";
                if ($out[3] == 1) {
                    $dvalue = ($fieldin) ? defconst($fieldin) : "";
                    $field = "<input type=\"text\" name=\"field[]\" value=\"".$dvalue."\" class=\"".$style."\" placeholder=\"".$dvalue."\"".$requir.">";
                } elseif ($out[3] == 2) {
                    $field = "<textarea name=\"field[]\" cols=\"15\" rows=\"5\" class=\"".$style."\"".$requir.">".$fieldin."</textarea>";
                } elseif ($out[3] == 3) {
                    $field = "<select name=\"field[]\" class=\"".$style."\"".$requir.">";
                    $field .= "<option value=\"\">"._NO."</option>";
                    $fieldcs = explode(",", $out[2]);
                    foreach ($fieldcs as $val) {
                        if ($val != "") {
                            $sel = ($val == $fieldin) ? " selected" : "";
                            $field .= "<option value=\"".$val."\"".$sel.">".$val."</option>\n";
                        }
                    }
                    $field .= "</select>";
                } elseif ($out[3] == 4) {
                    $field = datetime(1, "field[]", $fieldin, 16, $conf['style']);
                } elseif ($out[3] == 5) {
                    $field = datetime(2, "field[]", $fieldin, 10, $conf['style']);
                }
                $fields .= "<tr><td>".defconst($out[1]).":</td><td>".$field."</td></tr>";
            }
        }
        $i++;
    }
    return $fields;
}

# Fields out
function fields_out(mixed $fieldb, string $mod): ?string {
    global $conf;
    $mod = strtolower($mod);
    if ($fieldb && $mod) {
        $fieldc = $conf['fields'][$mod];
        $fieldb = explode("|", $fieldb);
        $fieldc = explode("||", $fieldc);
        $i = 0;
        $fields = "";
        foreach ($fieldc as $val) {
            if ($val != "" && !empty($fieldb[$i])) {
                preg_match("#(.*)\|(.*)\|(.*)\|(.*)#i", $val, $out);
                $fields .= defconst($out[1]).": ".$fieldb[$i]."<br>";
            }
            $i++;
        }
        return $fields;
    }
}

# Format domain
function domain(string $url, string $str = ''): string {
    $massiv = explode(",", $url);
    $str = intval($str);
    foreach ($massiv as $val) $dom[] = "<a href=\"".$val."\" target=\"_blank\" title=\""._DOWNLLINK."\">".(($str) ? cutstr(preg_replace("/http\:\/\/|www./", "", $val), $str) : preg_replace("/http\:\/\/|www./", "", $val))."</a>";
    return implode(", ", $dom);
}

# Check bot
function is_bot(): int|string {
 global $conf;
    $bots = explode(",", $conf['bots']);
    for ($i = 0; $i < count($bots); $i++) {
        list($uagent, $bname) = explode("=", $bots[$i]);
        if (preg_match("#".$uagent."#i", getAgent())) {
            $name = text_filter(substr($bname, 0, 25), 1);
            break;
        } else {
            $name = 0;
        }
    }
    return $name;
}
# Check referer from bot
function from_bot(): int|string {
 global $conf;
    $bots = explode(",", $conf['fbots']);
    for ($i = 0; $i < count($bots); $i++) {
        if (preg_match("#".$bots[$i]."#i", get_referer())) {
            $name = text_filter(substr($bots[$i], 0, 25), 1);
            break;
        } else {
            $name = 0;
        }
    }
    return $name;
}

# Check referer from Search Engines
function engines_word(string $refer): ?string {
    $engines = ["images.google." => ["q", "prev"], "bing.com" => "q", ".alot." => "q", "a993.com" => "q1", "abcsok." => "q", "alltheweb." => "q", "altavista." => "q", "aol." => ["q", "query", "encquery"], "aolsvc." => "query", "avantfind.com" => "keywords", "bonvote.com" => "search", "bonweb.com" => "search", "comcast.net" => "q", "conduit." => "q", "eniro.se" => "search_word", "excite." => "search", "google." => ["q", "as_q"], "gogo.ru" => "q", "yandex." => ["text", "query"], "ya.ru" => "text", "hotbot." => "query", "icerocket.com" => "q", "icq.com" => "q", "isheyka.com" => "q", "midco.net" => "q", "live.com" => "q", "msn." => "q", "yahoo." => ["p", "k"], "search." => "q", "kvasir.no" => "q", "myway.com" => "searchfor", "netscape." => ["q", "query"], "oceanfree.net" => "as_q", "qip.ru" => "query", "sweetim.com" => "q", "tut.by" => "query", "ukr.net" => "search_query", "search.oboz.ua" => "k", "search.www.infoseek.co.jp" => "qt", ".setooz.com" => "query", "toile.com" => "q", "vinden.nl" => "q", ".i.ua" => "q", ".mail.ru" => ["q", "tag"], ".onru.ru" => "q", "aport.ru" => "r", "find.ru" => "text", "gde.ru" => ["keywords", "query", "t", "search_query", "id"], "go.km.ru" => "sq", "meta.ua" => "q", "metabot.ru" => "st", "nerus.ru" => "query", "nigma.ru" => ["s", "pq"], "nova.rambler.ru" => "query", "poisk.ru" => "text", "protonet.ru" => "q", "rambler.ru" => "query", "tyndex.ru" => "pnam", "webalta.ru" => "q", "exactseek.com" => ["q", "query"], "lycos." => "query", "ask." => "q", "cnn." => "query", "looksmart." => "qt", "about." => "terms", "mamma." => "query", "gigablast." => "q", "voila." => "rdata", "virgilio." => "qs", "baidu." => "wd", "alice." => "qs", "najdi." => "q", "club-internet." => "q", "mama." => "query", "seznam." => "q", "netsprint." => "q", "szukacz." => "q", "yam." => "k", "pchome." => "q"];

    $refer= str_replace(["&#038;", "&amp;"], "&", $refer);
    $tmp = parse_url(urldecode(trim($refer)));
    $site = $tmp['host'];
    $str = $tmp['query'] ?? '';
    parse_str($str, $arr);

    foreach ($engines as $key => $value) {
        if (substr_count($site, $key)) {
            foreach ($arr as $k => $v) {
                if (is_array($value)) {
                    if (in_array($k, $value)) {
                        return $v;
                        break;
                    }
                } elseif ($k == $value) {
                    return $v;
                    break;
                } else {
                    return _NO;
                    break;
                }
            }
            break;
        }
    }
}

# Check user
function is_user(string $usr = ''): int {
 global $db, $conf, $user;
    static $usertrue;
    if (!isset($usertrue) && $user) {
        $uid = intval(substr($user[0], 0, 11));
        $una = htmlspecialchars(substr($user[1], 0, 25));
        $pwd = htmlspecialchars(substr($user[2], 0, 40));
        $ip = getIp();
        if ($uid != "" && $pwd != "") {
            if ($conf['users']['check'] == "0") {
                list($pass) = $db->sql_fetchrow($db->sql_query("SELECT user_password FROM ".PREFIX_DB."_users WHERE user_id = :uid AND user_name = :name", ['uid' => $uid, 'name' => $una]));
                if ($pass == $pwd && $pass != "") {
                    $usertrue = 1;
                    return 1;
                }
            } else {
                list($pass, $last_ip) = $db->sql_fetchrow($db->sql_query("SELECT user_password, user_last_ip FROM ".PREFIX_DB."_users WHERE user_id = :uid AND user_name = :name", ['uid' => $uid, 'name' => $una]));
                if ($pass == $pwd && $pass != "" && $last_ip == $ip && $last_ip != "") {
                    $usertrue = 1;
                    return 1;
                }
            }
        }
        $usertrue = 0;
        return 0;
    }
    if ($usertrue == 1) {
        return 1;
    } else {
        return 0;
    }
}

# Get user id
function is_user_id(string $name): int {
 global $db;
    $name = text_filter(substr($name, 0, 25));
    list($uid) = $db->sql_fetchrow($db->sql_query('SELECT user_id FROM '.PREFIX_DB.'_users WHERE user_name = :name', ['name' => $name]));
    return intval($uid);
}

# Check admin
function is_admin(string $adm = ''): int {
 global $db, $admin;
    static $admintrue;
    if (!empty($admin)) {
        if (!isset($admintrue)) {
            $id = intval(substr($admin[0], 0, 11));
            $name = htmlspecialchars(substr($admin[1], 0, 25));
            $pwd = htmlspecialchars(substr($admin[2], 0, 40));
            $ip = getIp();
            if ($id && $name && $pwd && $ip) {
                list($aname, $apwd, $aip) = $db->sql_fetchrow($db->sql_query('SELECT name, pwd, ip FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $id]));
                if ($aname == $name && $aname != "" && $apwd == $pwd && $apwd != "" && $aip == $ip && $aip != "") {
                    $admintrue = 1;
                    return $admintrue;
                }
            }
            $admintrue = 0;
            return $admintrue;
        } else {
            return $admintrue;
        }
    } else {
        return 0;
    }
}

# Get admin module names (stored as names)
function getAdminModuleNames(string $modules): array {
    $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
    return array_values(array_unique($list));
}

# Check modul admin
function is_admin_modul(string $modul): int {
 global $db, $admin;
    $aid = intval(substr($admin[0], 0, 11));
    $modul = addslashes(trim(substr($modul, 0, 25)));
    if ($modul == '') return 0;
    if (is_admin_god()) return 1;
    static $amodules = [];
    if (!isset($amodules[$aid])) {
        list($modules) = $db->sql_fetchrow($db->sql_query('SELECT modules FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid]));
        $modules = $modules ?? '';
        $names = getAdminModuleNames($modules);
        $new_modules = implode(',', $names);
        if ($new_modules !== $modules) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_admins SET modules = :modules WHERE id = :id', ['modules' => $new_modules, 'id' => $aid]);
        }
        $amodules[$aid] = $names ? array_fill_keys($names, 1) : [];
    }
    return isset($amodules[$aid][$modul]) ? 1 : 0;
}

# Check moderator
function is_moder(string $modul = ''): int {
    $modul = ($modul) ? addslashes(trim(substr($modul, 0, 25))) : 0;
    if ((is_admin() && is_admin_god()) || ($modul && is_admin() && is_admin_modul($modul))) {
        return 1;
    } else {
        return 0;
    }
}

# Search user name
function get_user(): void {
 global $db;
    $let = analyze_name(getVar('get', 'term', 'text', ''));
    if ($let) {
        $result = $db->sql_query('SELECT user_name FROM '.PREFIX_DB.'_users WHERE user_name LIKE :name ORDER BY user_name ASC', ['name' => $let.'%']);
        while(list($user_name) = $db->sql_fetchrow($result)) $name[]= "\"".$user_name."\"";
        echo "[".implode(", ", $name)."]";
    }
}

# Autocomplete user name
function get_user_search(string $id, string $val, int $maxlength, string $extraClass = '', string $required = ''): string {
 global $conf;
    $class = $extraClass ? "sl_field ".$extraClass : "sl_field";
    $req   = $required ? " required" : "";
    $cont = "<script>
    $(function() {
        $(\"#".$id."\").autocomplete({
            source: \"index.php?go=1&op=get_user\",
            minLength: ".$conf['slet']."
        });
    });
    </script>"
    ."<input type=\"text\" id=\"".$id."\" name=\"".$id."\" value=\"".$val."\" maxlength=\"".$maxlength."\" class=\"".$class."\" placeholder=\""._NICKNAME."\"".$req.">";
    return $cont;
}

# Analyze name
function analyze_name(mixed $name): string {
    $name = ($name) ? ((preg_match("#\"|\'|\.|\:|\;|\/|\*#", $name)) ? "" : $name) : "";
    return $name;
}

# URL types
function url_types(string $urls): string {
    $url = explode(",", $urls);
    $con = [];
    foreach ($url as $v) {
        $var    = parse_url($v);
        $scheme = !empty($var['scheme']) ? $var['scheme'] : "";
        if ($scheme == "ed2k") {
            $con[] = "eMule";
        } elseif ($scheme == "http") {
            $con[] = ucfirst(current(explode(".", str_replace("www.", "", $var['host']))));
        }
    }
    return $con ? implode(", ", array_unique($con)) : "";
}

# Check user
function check_user(): ?bool {
 global $user;
    if (is_user()) {
        $f = COUNTER_DIR.'/user.log';
        $un = text_filter(substr($user[1], 0, 25), 1);
        if (file_exists($f)) {
            $fun = file_get_contents($f);
            $fun = explode(",", $fun);
            foreach ($fun as $val) {
                if ($val != "" && $val == $un) {
                    return false;
                    break;
                }
            }
        }
        $fp = fopen($f, "ab");
        flock($fp, 2);
        fwrite($fp, $un.",");
        flock($fp, 3);
        fclose($fp);
        return true;
    } else {
        return false;
    }
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
            $db->sql_query("DELETE FROM ".PREFIX_DB."_session WHERE time < :past", ['past' => $past]);
            if (file_exists($sess_f)) unlink($sess_f);
            $fp = fopen($sess_f, "wb");
            fwrite($fp, $ctime);
            fclose($fp);
        }
        if (!empty($uname)) {
            if (!defined("ADMIN_FILE") && is_user()) {
                $uagent = getAgent();
                $uid= intval($user[0]);
                $db->sql_query("UPDATE ".PREFIX_DB."_users SET user_last_ip = :ip, user_lastvisit = NOW(), user_agent = :agent WHERE user_id = :uid", ['ip' => $ip, 'agent' => $uagent, 'uid' => $uid]);
            }
            $num = $db->sql_numrows($db->sql_query("SELECT id FROM ".PREFIX_DB."_session WHERE uname = :uname", ['uname' => $uname]));
            if ($num >= 1) {
                $db->sql_query('UPDATE '.PREFIX_DB.'_session SET time = :time, host_addr = :ip, guest = :guest, module = :module, url = :url WHERE uname = :uname', [':time' => $ctime, ':ip' => $ip, ':guest' => $guest, ':module' => $name, ':url' => $url, ':uname' => $uname]);
            } else {
                $db->sql_query("INSERT INTO ".PREFIX_DB."_session (uname, time, host_addr, guest, module, url) VALUES (:uname, :time, :ip, :guest, :module, :url)", ['uname' => $uname, 'time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'module' => $name, 'url' => $url]);
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
                $db->sql_query("DELETE FROM ".PREFIX_DB."_referer WHERE lid = :lid", ['lid' => 0]);
                unlink($refer_f);
                $fp = fopen($refer_f, "wb");
                fwrite($fp, $ctime);
                fclose($fp);
            }
            $ip = getIp();
            $uid = is_user() ? intval($user[0]) : 0;
            $link = text_filter($request);
            if (is_active('auto_links')) {
                list($exist) = $db->sql_fetchrow($db->sql_query("SELECT ip FROM ".PREFIX_DB."_referer WHERE ip = :ip AND lid != :lid", ['ip' => $ip, 'lid' => 0]));
                if ($exist) {
                    if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, refer, page, date, lid) VALUES (:uid, :name, :ip, :refer, :page, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'refer' => $referer, 'page' => $link, 'lid' => 0]);
                } else {
                    $result = $db->sql_query("SELECT link FROM ".PREFIX_DB."_auto_links");
                    while(list($slink) = $db->sql_fetchrow($result)) {
                        if (preg_match("#".$slink."#i", $referer)) {
                            $islink = 1;
                            break;
                        } else {
                            $islink = 0;
                        }
                    }
                    if ($islink) {
                        $db->sql_query("UPDATE ".PREFIX_DB."_auto_links SET hits = hits + 1 WHERE link = :link", ['link' => $slink]);
                        list($lid) = $db->sql_fetchrow($db->sql_query("SELECT id FROM ".PREFIX_DB."_auto_links WHERE link = :link", ['link' => $slink]));
                        $db->sql_query("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, refer, page, date, lid) VALUES (:uid, :name, :ip, :refer, :page, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'refer' => $referer, 'page' => $link, 'lid' => $lid]);
                    } else {
                        if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, refer, page, date, lid) VALUES (:uid, :name, :ip, :refer, :page, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'refer' => $referer, 'page' => $link, 'lid' => 0]);
                    }
                }
            } else {
                if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".PREFIX_DB."_referer (uid, name, ip, refer, page, date, lid) VALUES (:uid, :name, :ip, :refer, :page, NOW(), :lid)", ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'refer' => $referer, 'page' => $link, 'lid' => 0]);
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

# Backward-compatible wrapper
function head(): void {
    setHead();
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

# Backward-compatible wrapper
function foot(): void {
    setFoot();
}

# Log files report
function create_dump(string $dir, array &$log): void {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..') continue;
                $location = $dir.$file;
                if (filetype($location) == 'dir') {
                    create_dump($location.'/', $log);
                } else {
                    if (is_readable($location)) $log[$location] = md5_file($location);
                }
            }
            closedir($dh);
        }
    }
}

function write_dump(array $dump, string $file): bool {
    if ($fp = fopen($file, 'wb')) {
        $new = '';
        foreach ($dump as $location => $md5) $new .= $location.'||'.$md5."\n";
        flock($fp, 2);
        fwrite($fp, $new);
        flock($fp, 3);
        fclose($fp);
    }
    return ($fp) ? true : false;
}

function write_log(mixed $log, string $file): bool {
    global $conf;
    if ($fp = fopen($file, "ab")) {
        if (filesize($file) > $conf['security']['log_size']) {
            zip_compress($file, "config/logs/dump_log_".date("Y-m-d_H-i").".txt");
            unlink($file);
        }
        $log = ($log) ? implode("\n", $log) : _NO;
        flock($fp, 2);
        fwrite($fp, $log."\n"._DATE.": ".date(_TIMESTRING)."\n---\n");
        flock($fp, 3);
        fclose($fp);
    }
    return ($fp) ? true : false;
}

function diff_dump(array $dump, array $old): array|false {
    $log = [];
    foreach ($old as $string) {
        list($location, $md5) = explode("||", trim($string));
        $new[$location] = $md5;
    }
    foreach ($new as $location => $md5) {
        if (!isset($dump[$location])) $log[] = _D_DEL.": ".$location;
    }
    $filedump = dirname($_SERVER['PHP_SELF'])."/config/logs/dump.txt";
    $filelog = dirname($_SERVER['PHP_SELF'])."/config/logs/dump_log.txt";
    foreach ($dump as $location => $md5) {
        if (strpos($filedump, substr($location, 2)) !== false || strpos($filelog, substr($location, 2))) continue;
        if (!isset($new[$location])) {
            $log[] = _D_NEW.": ".$location;
        } elseif ($new[$location] != $dump[$location]) {
            $log[] = _D_EDIT.": ".$location;
        }
    }
    return (count($log) > 0) ? $log : false;
}

function filereport(): void {
 global $conf;
    if ($conf['security']['log_d']) {
        $sess_f = "config/counter/dump.txt";
        $sess_d = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = time() - intval($conf['security']['sess_d']);
        if ($sess_d < $past) {
            unlink($sess_f);
            $fp = fopen($sess_f, "wb");
            fwrite($fp, time());
            fclose($fp);

            $safe = ini_get("safe_mode") == "1" ? 1 : 0;
            if (!$safe && function_exists("set_time_limit")) set_time_limit(600);

            $dump = [];
            create_dump("./", $dump);
            if (file_exists("config/logs/dump.txt") && filesize("config/logs/dump.txt") != 0) {
                if ($log = diff_dump($dump, file("config/logs/dump.txt"))) sort($log);
            } else {
                $log = false;
            }
            write_log($log, "config/logs/dump_log.txt");
            write_dump($dump, "config/logs/dump.txt");
            if ($conf['security']['mail_d']) {
                $log = ($log) ? implode("<br>", $log) : _NO;
                $subject = $conf['sitename']." - "._SECURITY;
                $mmsg = $conf['sitename']." - "._SECURITY."<br><br>".$log."<br>"._DATE.": ".date(_TIMESTRING);
                mail_send($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
            }
        }
    }
}

# User and admin login report
function login_report(mixed $id, mixed $typ, mixed $login, mixed $pass): void {
 global $admin, $conf, $user;
    $id = ($id) ? "admin" : "user";
    if (($conf['security']['log_a'] && $id) || ($conf['security']['log_u'] && !$id)) {
        $typ = ($typ) ? _YES : _NO;
        $ip = getIp();
        $login = ($login) ? "\n"._NICKNAME.": ".substr($login, 0, 25) : "";
        $lpass = ($pass) ? "\n"._PASSWORD.": ".substr($pass, 0, 25) : "";
        $agent = getAgent();
        $url = text_filter(getenv("REQUEST_URI"));
        $ladmin = ($admin) ? "\n"._ADMIN.": ".substr($admin[1], 0, 25) : "";
        $luser = ($user) ? "\n"._USER.": ".substr($user[1], 0, 25) : "";
        $path = "config/logs/log_".$id.".txt";
        if ($fhandle = fopen($path, "ab")) {
            if (filesize($path) > $conf['security']['log_size']) {
                zip_compress($path, "config/logs/log_".$id."_".date("Y-m-d_H-i").".txt");
                unlink($path);
            }
            fwrite($fhandle, _INPUT.": ".$typ."\n"._IP.": ".$ip.$login.$lpass.$ladmin.$luser."\n"._URL.": ".$url."\n"._BROWSER.": ".$agent."\n"._DATE.": ".date(_TIMESTRING)."\n---\n");
            fclose($fhandle);
        }
    }
}

# Backup DB for MySQL 8.0+ & MariaDB 10+
function addBackupDb(): bool {
 global $db, $conf;

    if (!$conf['security']['log_b']) {
        return false;
    }

    // Logging start for performance analysis
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
    # * — any number of characters;
    # ? — one arbitrary character;
    # ^ — excludes table(s) from the list.

    # Examples:
    # slaed_*           — all tables starting with "slaed_" (all Invision Board forum tables)
    # slaed_*, ^slaed_session  — all tables starting with "slaed_", except "slaed_session"
    # slaed_s*s, ^slaed_session — all tables starting with "slaed_s" and ending with "s", except "slaed_session"
    # ^*s               — all tables except those ending with "s"
    # ^slaed_????       — all tables except those starting with "slaed_" with 4 chars after the underscore
    $ctables = "^ipb_*";

    $bsize = 0;

    // Server-Version via PDO
    try {
        $vres = $db->sql_query("SELECT VERSION() AS v");
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
        $db->sql_query("SET NAMES '".$charset."'");
        $last_charset = $charset;
    } else {
        $last_charset = "";
    }

    // FIX: Korrigierte Filter-Logik
    $tables = [];
    $res = $db->sql_query("SHOW TABLES");

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
    $res = $db->sql_query("SHOW TABLE STATUS");
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

    $db->sql_query("SET SQL_QUOTE_SHOW_CREATE = 1");

    foreach ($tables as $table) {
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$table)) {
            continue;
        }
        // FIX: Charset isset() Check
        if ($bmysql_ver > 40101 && isset($tab_charset[$table]) && $tab_charset[$table] != $last_charset) {
            if ($ccharset == "auto" && !empty($tab_charset[$table])) {
                $tcharset = preg_replace('#[^a-zA-Z0-9_\\-]#', '', (string)$tab_charset[$table]);
                if ($tcharset !== '') {
                    $db->sql_query("SET NAMES '".$tcharset."'");
                    $last_charset = $tcharset;
                }
            }
        }

        $res = $db->sql_query("SHOW CREATE TABLE `{$table}`");
        $tab = $res->fetch(PDO::FETCH_NUM);

        // For MariaDB 10+ do NOT use conditional comments
        if (isset($tab[1])) {
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n{$tab[1]};\n\n");
        }

        if (in_array($tab_type[$table], $bonly_create)) continue;

        $NumericColumn = [];
        $res = $db->sql_query("SHOW COLUMNS FROM `{$table}`");
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

            while ($res = $db->sql_query("SELECT * FROM `{$table}` LIMIT ".intval($from).", ".intval($limit))) {
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
                            $row[$k] = isset($row[$k]) ? $db->sql_value($row[$k]) : "NULL";
                        }
                    }

                    // Ãâ€ÃÂ¾ÃÂ±ÃÂ°ÃÂ²ÃÂ»Ã‘ÂÃÂµÃÂ¼ ÃÂ·ÃÂ°ÃÂ¿Ã‘ÂÃ‘â€šÃ‘Æ’Ã‘Å½ ÃÅ¸Ãâ€¢ÃÂ Ãâ€¢Ãâ€ Ã‘ÂÃ‘â€šÃ‘â‚¬ÃÂ¾ÃÂºÃÂ¾ÃÂ¹ (ÃÂºÃ‘â‚¬ÃÂ¾ÃÂ¼ÃÂµ ÃÂ¿ÃÂµÃ‘â‚¬ÃÂ²ÃÂ¾ÃÂ¹ ÃÂ¸ ÃÂ¿ÃÂ¾Ã‘ÂÃÂ»ÃÂµ Ã‘â‚¬ÃÂ°ÃÂ·ÃÂ´ÃÂµÃÂ»ÃÂµÃÂ½ÃÂ¸Ã‘Â)
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

# Check user acess
function is_acess(string $ids): bool {
 global $db, $user, $conf;
    if ($ids) {
        $id = explode("|", $ids);
        if (is_moder(isset($conf['name']))) {
            $isa = true;
        } elseif (is_user() && $id[1]) {
            $uid = intval($user[0]);
            $mid = array_values(array_filter(array_map('intval', explode(",", (string)$id[1])), static fn($v) => $v > 0));
            if ($mid) {
                $pp = [];
                $pm = ['uid' => $uid];
                foreach ($mid as $k => $gid) {
                    $ph = 'g'.$k;
                    $pp[] = ':'.$ph;
                    $pm[$ph] = $gid;
                }
                $sql = "SELECT COUNT(u.user_id) FROM ".PREFIX_DB."_users AS u LEFT JOIN ".PREFIX_DB."_groups AS g ON ((g.extra = 1 AND u.user_group = g.id) OR (g.extra != 1 AND u.user_points >= g.points)) WHERE u.user_id = :uid AND g.id IN (".implode(', ', $pp).")";
                list($uid) = $db->sql_fetchrow($db->sql_query($sql, $pm));
            } else {
                $uid = 0;
            }
            $isa = ($uid) ? true : false;
        } elseif (is_user() && !$id[1]) {
            $isa = (1 >= $id[0]) ? true : false;
        } else {
            $isa = (0 >= $id[0] && !$id[1]) ? true : false;
        }
    } else {
        $isa = false;
    }
    return $isa;
}

# Format categories select
function getcat(string $modul = '', int $id = 0, string $selectName = '', string $extraClass = '', string $emptyOption = '', string $noSelect = ''): string {
 global $db, $conf;
    $modul = analyze($modul);
    $conf['name'] = $conf['name'] ?? $modul;
    $class  = $extraClass ? "sl_field ".$extraClass : "sl_field";
    if ($modul) {
        $where  = 'WHERE modul = :modul ORDER BY ordern';
        $params = ['modul' => $modul];
    } else {
        $where  = 'ORDER BY ordern';
        $params = [];
    }
    $result = $db->sql_query('SELECT id, title, parentid, auth_view FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->sql_numrows($result) > 0) {
        $content = (!$noSelect) ? "<select name=\"".$selectName."\" title=\""._CATEGORIES."\" class=\"".$class."\">" : "";
        while (list($cid, $title, $parentid, $auth_view) = $db->sql_fetchrow($result)) if (is_acess($auth_view)) $massiv[$cid] = [defconst($title), $parentid];
        foreach ($massiv as $key => $val) {
            $cont[$key] = $val[0];
            $flag = $val[1];
            while ($flag != 0) {
                $cont[$key] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$cont[$key];
                $flag = intval($massiv[$flag][1]);
            }
            $sel     = ($id == $key) ? " selected" : "";
            $content .= "<option value=\"".$key."\"".$sel.">".$cont[$key]."</option>";
        }
        return (!$noSelect) ? $content."</select>" : $content;
    } elseif ($emptyOption) {
        return "<select name=\"".$selectName."\" title=\""._CATEGORIES."\" class=\"".$class."\">".$emptyOption."</select>";
    }
    return '';
}

# Format categories links
function catlink(string $mod = '', int $id = 0, string $sep = '', string $home = ''): string {
 global $db, $conf;
    $mod     = analyze($mod);
    $sep     = $sep ? " ".urldecode($sep)." " : " ".urldecode($conf['defis'])." ";
    $content = $home ? "<a href=\"index.php?name=".$conf['name']."\" title=\"".$home."\">".$home."</a>".$sep : "";
    if ($mod) {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $mod];
    } else {
        $where  = '';
        $params = [];
    }
    $result = $db->sql_query('SELECT id, title, parentid FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->sql_numrows($result) > 0) {
        while (list($cid, $title, $parentid) = $db->sql_fetchrow($result)) $massiv[$cid] = [defconst($title), $parentid];
        foreach ($massiv as $key => $val) {
            $flag = $val[1];
            $cont[$key] = ($flag != 0) ? $val[0] : "<a href=\"index.php?name=".$conf['name']."&amp;cat=".$key."\" title=\"".$val[0]."\">".$val[0]."</a>";
            while ($flag != 0) {
                $cont[$key] = "<a href=\"index.php?name=".$conf['name']."&amp;cat=".$flag."\" title=\"".$massiv[$flag][0]."\">".$massiv[$flag][0]."</a>".$sep."<a href=\"index.php?name=".$conf['name']."&amp;cat=".$key."\" title=\"".$val[0]."\">".$cont[$key]."</a>";
                $flag = intval($massiv[$flag][1]);
            }
            if ($id == $key) $content .= $cont[$key];
        }
        return $content;
    }
    return '';
}

# Format categories IDs
function catids(string $mod = '', int $id = 0): string {
 global $db;
    $mod     = analyze($mod);
    $content = '';
    if ($mod) {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $mod];
    } else {
        $where  = '';
        $params = [];
    }
    $result = $db->sql_query('SELECT id, parentid FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->sql_numrows($result) > 0) {
        while (list($cid, $parentid) = $db->sql_fetchrow($result)) $massiv[$cid] = [$parentid];
        foreach ($massiv as $key => $val) {
            $cont[$key] = $key;
            $flag = $val[0];
            while ($flag != 0) {
                $cont[$key] = $flag.", ".$cont[$key];
                $flag = intval($massiv[$flag][0]);
            }
            if ($id == $key) $content .= $cont[$key];
        }
        return $content;
    }
    return '';
}

# Format categories IDs from module
function catmids(string $modul, string $field): string {
 global $db, $conf, $locale;
    if ($conf['multilingual']) {
        $where  = 'WHERE modul = :modul AND (language = :locale OR language = \'\')';
        $params = ['modul' => $modul, 'locale' => $locale];
    } else {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $modul];
    }
    $result = $db->sql_query('SELECT id, auth_read FROM '.PREFIX_DB.'_categories '.$where.' ORDER BY id', $params);
    while (list($cid, $auth_read) = $db->sql_fetchrow($result)) if (is_acess($auth_read)) $catid[] = $cid;
    return isset($catid) ? 'AND '.$field.' IN ('.implode(', ', $catid).')' : '';
}

# Length end filter
function cutstr(mixed $strip, int $size, string $type = ''): string {
    $strip = (string)$strip;
    $size = (int)$size;
    if (!$type) {
        $end = '&hellip;';
    } elseif ($type == '1') {
        $end = '.';
    } elseif ($type == '2') {
        $end = '';
    }
    if (mb_strlen($strip, 'utf-8') > $size) $strip = mb_substr($strip, 0, $size, 'utf-8').$end;
    return $strip;
}

# Check module
function is_active(string $mod, string $view = ''): int {
    global $conf;
    static $list = null;
    if ($list === null) {
        $list = [];
        foreach ($conf['modules'] as $name => $item) {
            if (empty($item['active'])) continue;
            $mview = intval($item['view'] ?? 0);
            if (!isset($list[$mview])) $list[$mview] = [];
            $list[$mview][$name] = 1;
        }
    }
    $vnum = intval($view);
    return isset($list[$vnum][$mod]) ? 1 : 0;
}

# Convert Markdown+BB source to safe HTML.
# Safe mode (true): escapes HTML, URL allowlist — for user content.
# Safe mode (false): allows raw HTML blocks + admin BB tags.
function filterMarkdown(string $src, bool $safe = true, string $mod = ''): string {
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
            return trim(strtr($this->filterMain($src, $safe), $this->stash));
        }

        // Same pipeline, but WITHOUT resetting stash/salt (used for nested [quote]/[hide])
        private function filterNest(string $src, bool $safe): string {
            return $this->filterMain($src, $safe);
        }

        private function filterMain(string $src, bool $safe): string {
            $src = str_replace(["\r\n", "\r"], "\n", $src);
            $src = $this->filterBbBlocks($src, $safe);
            $src = $this->filterFencedCode($src);
            $src = $this->filterIndentedCode($src);
            $src = $this->filterInlineCode($src);
            $src = $this->filterBlocks($src, $safe);
            return $src;
        }

        // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

        // â”€â”€ BB blocks (stash before Markdown parsing) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

        private function filterBbBlocks(string $src, bool $safe): string {
            // [hr] (legacy)
            $src = preg_replace('/\[hr\]/si', $this->addStash('<hr>'), $src) ?? $src;

            // [li] (legacy bullet)
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

            // [usehtml]...[/usehtml] (admin only)
            $src = preg_replace_callback(
                '/\[usehtml\](.*?)\[\/usehtml\]/si',
                function(array $m) use ($safe): string {
                    if ($safe) return $m[0];
                    $html = htmlspecialchars_decode(replace_break($m[1]), ENT_QUOTES);
                    return $this->addStash($html);
                },
                $src
            ) ?? $src;

            // [usephp]...[/usephp] (admin only)
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

            // [quote]...[/quote] (nested, innermost first)
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

            // [hide]...[/hide] (nested, innermost first)
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

        // â”€â”€ Code protection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

        // â”€â”€ Blocks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

                if (!$safe && preg_match('/^<\/?(?:div|section|article|aside|nav|header|footer|main|pre|ul|ol|table|figure)[\s>\/]/i', $trim)) {
                    $raw = '';
                    while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++]."\n"; }
                    $out .= $this->addStash($raw);
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

        // â”€â”€ Inlines: Markdown + BB â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

        private function filterInlines(string $src, bool $safe): string {

            // â”€â”€ BB inline â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

            // ed2k links — must come BEFORE generic [url] patterns
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

            // â”€â”€ Markdown inline â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

# Decode BB (shim — delegates to filterMarkdown for backward compatibility)
function bb_decode(string $src, string $mod = '', string $id = ''): string {
    $out = filterMarkdown($src, false, $mod); // backward compat: HTML passes through as in old bb_decode
    return search_replace($out, $mod);
}

# Format PHP code
function encode_php(array $text): string {
 global $conf;
    static $sname;

    $replace = isset($text[2]) ? trim($text[2]) : trim($text[1]);
    $cname = isset($text[2]) ? analyze($text[1]) : 'php';

    $from = ['bash', 'cpp', 'csharp', 'css', 'delphi', 'diff', 'groovy', 'java', 'jscript', 'php', 'plain', 'python', 'ruby', 'scala', 'sql', 'vb', 'xml'];
    $to = ['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'];
    $cname = str_ireplace($from, $to, $cname);
    $ucname = strtolower($cname);

    $in = ['&#034;', '&quot;', '&#036;', '&dollar;', '&#038;', '&amp;', '&#039;', '&apos;', '&#060;', '&lt;', '&#062;', '&gt;', '&#092;', '&bsol;'];
    $out = ["\"", "\"", "$", "$", "&", "&", "'", "'", "<", "<", ">", ">", "\\", "\\"];
    $replace = ($conf['syntax'] <= 1) ? str_replace($in, $out, $replace) : $replace;
    $replace = preg_replace('#<br.*>#i', '', $replace);

    if (!$conf['syntax']) {
        if (preg_match("#<\?(php)?[^[:graph:]]#", $replace)) {
            $replace = highlight_string($replace, true);
        } else {
            $replace = preg_replace("#&lt;\?php&nbsp;#", "", highlight_string("<?php ".$replace, true));
        }
        $format = str_replace("&nbsp;&nbsp;", "&nbsp; ", $replace);
    } elseif ($conf['syntax'] == 1) {
        $replace = explode("\n", str_replace(["\r\n", "\r"], "\n", $replace));
        $count = 1;
        $format = "";
        foreach ($replace as $code) {
            $bgcolor = ($count % 2) ? "background-color: #fafafa;" : "background-color: #fff;";
            $format .= "<tr style=\"".$bgcolor."\"><td style=\"vertical-align: top;\">".$count."</td>";
            $count++;
            if (preg_match("#<\?(php)?[^[:graph:]]#", $code)) {
                $format .= "<td style=\"width: 100%;\">".highlight_string($code, true)."</td></tr>";
            } else {
                $format .= "<td style=\"width: 100%;\">".preg_replace("#&lt;\?php&nbsp;#", "", highlight_string("<?php ".$code, true))."</td></tr>";
            }
        }
        $replace = str_replace("&nbsp;&nbsp;", "&nbsp; ", $format);
        $format = "<table class=\"sl_table_form\">".$replace."</table>";
    } elseif ($conf['syntax'] == 2) {
        if ($sname != $cname) {
            $scripts = "<script src=\"plugins/syntaxhighlighter/scripts/shCore.js\"></script>";
            $scripts .= (file_exists("plugins/syntaxhighlighter/scripts/shBrush".$cname.".js")) ? "<script src=\"plugins/syntaxhighlighter/scripts/shBrush".$cname.".js\"></script>" : "<script src=\"plugins/syntaxhighlighter/scripts/shBrushPhp.js\"></script>";
            $scripts .= "<script>
                SyntaxHighlighter.config.clipboardSwf = 'plugins/syntaxhighlighter/scripts/clipboard.swf';
                SyntaxHighlighter.all();
            </script>";
            $sname = $cname;
        } else {
            $scripts = "";
        }
        $format = $scripts."<pre class=\"brush: ".$ucname.";\">".$replace."</pre>";
    }
    return setTemplateBasic('code', ['{%title%}' => $cname.' - '._CODE, '{%content%}' => $format]);
}

# Search and replace
function search_replace(string $sourse, string $mod): string {
    global $conf;
    $mod = ($mod && isset($conf['replace'][$mod])) ? $conf['replace'][$mod] : "";
    if ($mod) {
        $mod = explode("||", $mod);
        foreach ($mod as $word) {
            if ($word != "") {
                $warray = explode("|", $word);
                if ($warray[0]) {
                    preg_match_all("#<[^>]*>#", $sourse, $tags);
                    array_unique($tags);
                    $taglist = [];
                    $k = 0;
                    foreach($tags[0] as $i) {
                        $k++;
                        $taglist[$k] = $i;
                        $sourse = str_replace($i, "<".$k.">", $sourse);
                    }
                    $sourse = preg_replace("#".$warray[0]."#i", $warray[1], $sourse);
                    foreach($taglist as $k => $i) $sourse = str_replace("<".$k.">", $i, $sourse);
                }
            }
        }
    }
    return $sourse;
}

# Admin mail add info
function addmail(int $id, string $mod, string $username = '', string $title = '', bool $isComment = false, string $text = ''): void {
 global $db, $conf, $locale;
    $mod = analyze($mod);
    if ($id && $mod) {
        $subject = $isComment ? $conf['sitename']." - ".$title." - "._COMMENT : $conf['sitename']." - ".$title;
        $puname  = $username ? text_filter(substr($username, 0, 25)) : _ANONYM;
        $message = $isComment ? str_replace("[text]", sprintf(_ADDMAILC, $puname, $title, $text), $conf['mtemp']) : str_replace("[text]", sprintf(_ADDMAIL, $puname, $title), $conf['mtemp']);
        $params = [];
        $where = ' WHERE smail = \'1\'';
        if ($conf['multilingual']) {
            $where .= ' AND (lang = :lang OR lang = \'\')';
            $params['lang'] = $locale;
        }
        $result = $db->sql_query('SELECT id, email, super, modules FROM '.PREFIX_DB.'_admins'.$where.' ORDER BY id', $params);
        while (list($id, $email, $super, $modules) = $db->sql_fetchrow($result)) {
            if ($super) {
                mail_send($email, $conf['adminmail'], $subject, $message, 1, 1);
            } else {
                $amid = getAdminModuleNames($modules);
                $new_modules = implode(',', $amid);
                if ($new_modules !== $modules) {
                    $db->sql_query('UPDATE '.PREFIX_DB.'_admins SET modules = :modules WHERE id = :id', ['modules' => $new_modules, 'id' => $id]);
                }
                foreach ($amid as $val) {
                    if ($val != '' && $val == $mod) {
                        mail_send($email, $conf['adminmail'], $subject, $message, 1, 1);
                        break;
                    }
                }
            }
        }
    }
}

# Mail check
function checkemail(string $mail): array {
 global $stop;
    $mail = strtolower(text_filter($mail, 1));
    if ((!$mail) || ($mail=="") || (!preg_match("#^[_\.a-z0-9-]+@([a-z0-9_-]+\.)+[a-z]{2,6}$#", $mail))) $stop[] = _ERROR1."<br>"._ERROR2." (<b>email@domain.com</b>)";
    if ((strlen($mail) >= 4) && (substr($mail, 0, 4) == "www.")) $stop[] = _ERROR1."<br>"._ERROR3." (<b>www.</b>)";
    if (strrpos($mail, " ") > 0) $stop[] = _ERROR1."<br>"._ERROR4.".";
    return $stop;
}

# Format add block
function addblocks(string $str): ?string {
 global $blocks, $blocks_c, $home, $showbanners, $foot, $db, $conf, $foot;
    preg_match_all("#{%BLOCKS([^%]+)%}#iUs", $str, $blk);
    $ci = count($blk[1]);
    for ($i = 0; $i < $ci; $i++) {
        $blk[0][$i] = '#'.$blk[0][$i].'#';
        $telo = trim($blk[1][$i]);
        $pos = strtolower($telo[0]);
        switch($pos) {
            case 'l':
            if ($blocks == "" || $blocks == "0"|| $blocks == "1") {
                ob_start();
                getBlocks('l');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'r':
            if ($blocks == "" || $blocks == "0"|| $blocks == "2") {
                ob_start();
                getBlocks('r');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'c':
            if ($blocks_c == "" || $blocks_c == "0" || $blocks_c == "1") {
                ob_start();
                getBlocks('c');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'd':
            if ($blocks_c == "" || $blocks_c == "0"|| $blocks_c == "2") {
                ob_start();
                getBlocks('d');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'b':
            getBlocks('b');
            $blk[1][$i] = $showbanners;
            break;
            case 'f':
            getBlocks('f');
            $blk[1][$i] = $foot;
            break;
            case 'm':
            $blk[1][$i] = ($home == 1) ? setMessageShow() : '';
            break;
            case 't':
            $blk[1][$i] = ($conf['db_t'] == '1') ? getTimeLoads() : '';
            break;
            case 'v':
            $cvar = explode(",", $conf['variables']);
            $blk[1][$i] = (!$cvar[0] && ($conf['var_view'] || (is_admin() && !$conf['var_view']))) ? "<div>".getVariables()."</div>" : "";
            break;
            default:
            $telo = explode(",", $telo);
            ob_start();
            getBlocks($telo[0], $telo[1]);
            $blk[1][$i] = ob_get_clean();
            break;
        }
    }
    return preg_replace($blk[0], $blk[1], $str);
}

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
        $result = $db->sql_query("SELECT bid, bkey, title, content, url, blockfile, view, expire, action, bposition, which FROM ".PREFIX_DB."_blocks WHERE active = '1' ".$querylang." ORDER BY weight ASC", $qlang_params);
        while(list($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $which) = $db->sql_fetchrow($result)) {
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
                        $db->sql_query("UPDATE ".PREFIX_DB."_blocks SET active = '0', expire = '0' WHERE bid = :bid", ['bid' => $bid]);
                        return;
                    } elseif ($action == "r") {
                        $db->sql_query("DELETE FROM ".PREFIX_DB."_blocks WHERE bid = :bid", ['bid' => $bid]);
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

# Format block
function render_blocks(string $side, string $blockfile, string $blocktitle, string $content, mixed $bid, string $url): ?string {
 global $showbanners, $foot;
    if ($url == '') {
        $blocktitle = defconst($blocktitle);
        if ($blockfile != '') {
            if (file_exists('blocks/'.$blockfile)) {
                include('blocks/'.$blockfile);
            } else {
                $content = '<div class="sl_center">'._BLOCKPROBLEM.'</div>';
            }
        }
        if (!isset($content) || empty($content)) $content = '<div class="sl_center">'._BLOCKPROBLEM2.'</div>';
        switch($side) {
            case 'b':
            $showbanners = $content;
            break;
            case 'f':
            $foot = $content;
            break;
            case 'n':
            echo $content;
            break;
            case 'p':
            return $content;
            break;
            case 'o':
            return setTemplateBlock('', ['{%title%}' => $blocktitle, '{%content%}' => $content]);
            break;
            default:
            echo setTemplateBlock('', ['{%title%}' => $blocktitle, '{%content%}' => $content]);
            break;
        }
    } else {
        rss_load($bid);
    }
}

# Format rating
function rating(): void {
 global $db, $conf, $user;
    $id   = getVar('get', 'id',   'num',  0);
    $typ  = analyze(getVar('get', 'typ',  'text', ''));
    $mod  = analyze(getVar('get', 'mod',  'text', ''));
    $rate = min(5, getVar('get', 'rate', 'num', 0));
    $stl  = getVar('get', 'stl',  'num',  0);
    $con = explode("|", $conf['ratings'][strtolower($mod)]);
    if ($id && $mod) {
        $query = '';
        if ($mod == "account") {
            $query = "SELECT user_votes, user_totalvotes FROM ".PREFIX_DB."_users WHERE user_id = :id";
        } elseif ($mod == "faq") {
            $query = "SELECT ratings, score FROM ".PREFIX_DB."_faq WHERE fid = :id";
        } elseif ($mod == "files") {
            $query = "SELECT votes, totalvotes FROM ".PREFIX_DB."_files WHERE lid = :id";
        } elseif ($mod == "forum") {
            $query = "SELECT ratings, score FROM ".PREFIX_DB."_forum WHERE id = :id";
        } elseif ($mod == "help") {
            $query = "SELECT ratings, score FROM ".PREFIX_DB."_help WHERE sid = :id";
        } elseif ($mod == "jokes") {
            $query = "SELECT ratingtot, rating FROM ".PREFIX_DB."_jokes WHERE jokeid = :id";
        } elseif ($mod == "links") {
            $query = "SELECT votes, totalvotes FROM ".PREFIX_DB."_links WHERE lid = :id";
        } elseif ($mod == "media") {
            $query = "SELECT votes, totalvotes FROM ".PREFIX_DB."_media WHERE id = :id";
        } elseif ($mod == "news") {
            $query = "SELECT ratings, score FROM ".PREFIX_DB."_news WHERE sid = :id";
        } elseif ($mod == "pages") {
            $query = "SELECT ratings, score FROM ".PREFIX_DB."_pages WHERE pid = :id";
        } elseif ($mod == "shop") {
            $query = "SELECT votes, totalvotes FROM ".PREFIX_DB."_products WHERE id = :id";
        }
        if ($query == '') {
            return;
        }
        $ip = getIp();
        $past = time() - intval($con[0]);
        $cmod = substr($mod, 0, 2)."-".$id;
        $cookies = isset($_COOKIE[$cmod]) ? intval($_COOKIE[$cmod]) : "";
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->sql_query("DELETE FROM ".PREFIX_DB."_rating WHERE time < :past AND modul = :mod", ['past' => $past, 'mod' => $mod]);
        list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_rating WHERE (mid = :id AND modul = :mod AND host = :ip) OR (mid = :id2 AND modul = :mod2 AND uid = :uid AND uid != '0')", ['id' => $id, 'mod' => $mod, 'ip' => $ip, 'id2' => $id, 'mod2' => $mod, 'uid' => $uid]));
        if ($cookies == $id || $num > 0) {
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query($query, ['id' => $id]));
            echo ajax_rating(2, "", "", $votes, $totalvotes, "", $stl);
        } elseif (!$cookies && !$num && !$rate) {
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query($query, ['id' => $id]));
            if (intval($votes)) {
                $votnum = $votes;
                $votes = $votes;
            } else {
                $votnum = 0;
                $votes = 1;
            }
            $width = number_format($totalvotes / $votes, 2) * 20;
            $result = substr($totalvotes / $votes, 0, 4);
            if (intval($votes) && intval($totalvotes)) {
                $title = _RATING.": ".$result."/".$votes." "._AVERAGESCORE.": ".$result;
                $nrate = "sl_rate-num sl_rate-is";
            } else {
                $title = _RATING.": 0/0 "._AVERAGESCORE.": 0";
                $nrate = "sl_rate-num";
            }
            if ($stl == 1) {
                echo "<span class=\"sl_none\">".$result."</span>
                <div class=\"sl_rate-like\">
                    <p OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=1&amp;stl=1', ''); return false;\" title=\""._RATE1."\" class=\"sl_rate-minus sl_out\">
                    <p OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=5&amp;stl=1', ''); return false;\" title=\""._RATE5."\" class=\"sl_rate-plus sl_out\">
                </div><span class=\"".$nrate."\" title=\"".$title."\">".$result."</span>";
            } else {
                echo "<ul class=\"sl_urating\">
                    <li class=\"sl_crating\" style=\"width:".$width."%;\"></li>
                    <li><div OnMouseOver=\"this.className='sl_over1';\" OnMouseOut=\"this.className='sl_out1';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=1', ''); return false;\" title=\""._RATE1."\" class=\"sl_out1\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over2';\" OnMouseOut=\"this.className='sl_out2';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=2', ''); return false;\" title=\""._RATE2."\" class=\"sl_out2\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over3';\" OnMouseOut=\"this.className='sl_out3';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=3', ''); return false;\" title=\""._RATE3."\" class=\"sl_out3\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over4';\" OnMouseOut=\"this.className='sl_out4';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=4', ''); return false;\" title=\""._RATE4."\" class=\"sl_out4\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over5';\" OnMouseOut=\"this.className='sl_out5';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id."&amp;typ=".$typ."&amp;mod=".$mod."&amp;rate=5', ''); return false;\" title=\""._RATE5."\" class=\"sl_out5\"></div></li>
                </ul><span class=\"".$nrate."\" title=\""._VOTES."\">".$votnum."</span>";
            }
        } elseif (!$cookies && !$num && $rate) {
            setcookie(substr($mod, 0, 2)."-".$id, $id, time() + intval($con[0]));
            $new = time();
            $inserted = $db->sql_query("INSERT INTO ".PREFIX_DB."_rating (mid, modul, time, uid, host) VALUES (:mid, :modul, :time, :uid, :host)", ['mid' => $id, 'modul' => $mod, 'time' => $new, 'uid' => $uid, 'host' => $ip]);
            if ($inserted) {
                if ($mod == "account" || $mod == "members") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_users SET user_votes = user_votes + 1, user_totalvotes = user_totalvotes + :rate WHERE user_id = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(2);
                } elseif ($mod == "faq") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_faq SET score = score + :rate, ratings = ratings + 1 WHERE fid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(8);
                } elseif ($mod == "files") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_files SET votes = votes + 1, totalvotes = totalvotes + :rate WHERE lid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(12);
                } elseif ($mod == "forum") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_forum SET score = score + :rate, ratings = ratings + 1 WHERE id = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(15);
                } elseif ($mod == "help") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_help SET score = score + :rate, ratings = ratings + 1 WHERE sid = :id", ['rate' => $rate, 'id' => $id]);
                } elseif ($mod == "gallery") {
                    #$db->sql_query("UPDATE ".PREFIX_DB."_gallery SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE lid = '".$id."'");
                    update_points(18);
                } elseif ($mod == "jokes") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_jokes SET rating = rating + :rate, ratingtot = ratingtot + 1 WHERE jokeid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(20);
                } elseif ($mod == "links") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_links SET votes = votes + 1, totalvotes = totalvotes + :rate WHERE lid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(24);
                } elseif ($mod == "media") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_media SET votes = votes + 1, totalvotes = totalvotes + :rate WHERE id = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(27);
                } elseif ($mod == "multimedia") {
                    #$db->sql_query("UPDATE ".PREFIX_DB."_multimedia SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE id = '".$id."'");
                    update_points(30);
                } elseif ($mod == "news") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_news SET score = score + :rate, ratings = ratings + 1 WHERE sid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(33);
                } elseif ($mod == "pages") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_pages SET score = score + :rate, ratings = ratings + 1 WHERE pid = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(37);
                } elseif ($mod == "shop") {
                    $db->sql_query("UPDATE ".PREFIX_DB."_products SET votes = votes + 1, totalvotes = totalvotes + :rate WHERE id = :id", ['rate' => $rate, 'id' => $id]);
                    update_points(41);
                }
            }
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query($query, ['id' => $id]));
            echo ajax_rating(2, "", "", $votes, $totalvotes, "", $stl);
        }
    }
}

# Format BB Code and Smilies
function textarea(string $id, string $name, string $var, string $mod, int $rows, string $placeholder = '', string $required = ''): string {
 global $admin, $op, $user, $conf;
    $placeholder = $placeholder ? " placeholder=\"".$placeholder."\"" : "";
    $required    = $required ? " required" : "";
    $stloc = substr(_LOCALE, 0, 2);
    $desc = $var ?: save_text(getVar('post', $name, 'raw', ''));
    $uploads_data = include('config/uploads.php');
    $conf['uploads'] = $uploads_data['uploads'] ?? [];
    $con = explode("|", (string)($conf['uploads'][strtolower($mod)] ?? ''));
    $style = (defined('ADMIN_FILE')) ? ' sl_form' : ' '.$conf['style'];
    $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
    if ((defined("ADMIN_FILE") && $editor == 1) || (!defined("ADMIN_FILE") && $conf['redaktor'] == 1)) {
        $code = ($id == 1) ? "<script src=\"plugins/system/insert-code.js\"></script>" : "";
        $code .= "<table class=\"sl_table_form\"><tr><td><div class=\"sl_bb-editor\">
        <div class=\"sl_bb-panel\">
            <div class=\"sl_pos_right\">
                <span OnClick=\"RowsTextarea(1, '".$id."')\" class=\"sl_bb_plus\" title=\""._EPLUS."\"></span>
                <span OnClick=\"RowsTextarea(0, '".$id."')\" class=\"sl_bb_minus\" title=\""._EMINUS."\"></span>
            </div>
            <span OnClick=\"InsertCode('b', '', '', '', '".$id."')\" class=\"sl_bb_b\" title=\""._EBOLD."\"></span>
            <span OnClick=\"InsertCode('i', '', '', '', '".$id."')\" class=\"sl_bb_i\" title=\""._EITALIC."\"></span>
            <span OnClick=\"InsertCode('u', '', '', '', '".$id."')\" class=\"sl_bb_u\" title=\""._EUNDERLINE."\"></span>
            <span OnClick=\"InsertCode('s', '', '', '', '".$id."')\" class=\"sl_bb_s\" title=\""._ESTRIKET."\"></span>
            <span OnClick=\"InsertCode('li', '', '', '', '".$id."')\" class=\"sl_bb_li\" title=\""._ELI."\"></span>
            <span OnClick=\"InsertCode('hr', '', '', '', '".$id."')\" class=\"sl_bb_hr\" title=\""._EHR."\"></span>
            <div class=\"sl_bb_sep\"></div>
            <span OnClick=\"InsertCode('left', '', '', '', '".$id."')\" class=\"sl_bb_left\" title=\""._ELEFT."\"></span>
            <span OnClick=\"InsertCode('center', '', '', '', '".$id."')\" class=\"sl_bb_center\" title=\""._ECENTER."\"></span>
            <span OnClick=\"InsertCode('right', '', '', '', '".$id."')\" class=\"sl_bb_right\" title=\""._ERIGHT."\"></span>
            <span OnClick=\"InsertCode('justify', '', '', '', '".$id."')\" class=\"sl_bb_justify\" title=\""._EYUSTIFY."\"></span>
            <div class=\"sl_bb_sep\"></div>
            <span OnClick=\"InsertCode('hide', '', '', '', '".$id."')\" class=\"sl_bb_hide\" title=\""._HIDE."\"></span>
            <span OnClick=\"InsertCode('url', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_link\" title=\""._EURL."\"></span>
            <span OnClick=\"InsertCode('mail', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_mail\" title=\""._EEMAIL."\"></span>
            <span OnClick=\"InsertCode('img', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_img\" title=\""._EIMG."\"></span>
            <!-- <span OnClick=\"InsertCode('media', '"._EMEDIA."', '', '', '".$id."')\" class=\"sl_bb_media\" title=\""._EMEDIA."\"></span> -->
            <span OnMouseOver=\"CopyText();\" OnClick=\"InsertCode('quote', '"._JQUOTE."', '', '', '".$id."')\" class=\"sl_bb_quote\" title=\""._EQUOTE."\"></span>
            <!-- <span OnClick=\"InsertCode('spoiler', '"._ESPOIL."', '', '', '".$id."')\" class=\"sl_bb_spoiler\" title=\""._ESPOIL."\"></span> -->
        </div>";
        $code .= "<textarea id=\"".$id."\" name=\"".$name."\" cols=\"65\" rows=\"".$rows."\" OnKeyPress=\"TransliteFeld(this, event)\" OnSelect=\"FieldName(this, '".$id."')\" OnClick=\"FieldName(this, '".$id."')\" OnKeyUp=\"FieldName(this, '".$id."')\" class=\"sl_field".$style."\"".$placeholder.$required.">".replace_break($desc)."</textarea>
        <div class=\"sl_bb-panel\">
            <div class=\"sl_pos_right\">
                <div class=\"sl_drop\">
                    <span OnClick=\"HideShow('i-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_info\" title=\""._INFO."\"></span>
                    <div id=\"i-form-".$id."\" class=\"sl_drop-form\">"._INFO_BB." ".$conf['version']."</div>
                </div>
            </div>";
            if ((defined("ADMIN_FILE") && $con[10] == 1) || (is_user() && $con[10] == 1) || (!is_user() && $con[11] == 1)) $code .= "<span OnClick=\"HideShow('af-form-".$id."', 'slide', 'up', 500); AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id."&amp;dir=".$mod."', ''); return false;\" class=\"sl_bb_file\" title=\""._EUPLOAD."\"></span>";
            $code .= "<div class=\"sl_drop\">
                <span OnClick=\"HideShow('s-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_smile\" title=\""._ESMILIE."\"></span>
                <div id=\"s-form-".$id."\" class=\"sl_drop-form\">";
                $i = 1;
                $dir = opendir(img_find("smilies"));
                while (false !== ($entry = readdir($dir))) {
                    if (preg_match("#(\.gif)$#i", $entry) && $entry != "." && $entry != "..") {
                        $i = ($i < 10) ? "0".$i : $i;
                        $code .= " <img src=\"".img_find("smilies/".$i.".gif")."\" OnClick=\"InsertCode('smilies', ' *".$i."', '', '', '".$id."');\" style=\"cursor: pointer; margin: 3px 2px 0px 0px;\" alt=\""._SMILIE." - ".$i."\" title=\""._SMILIE." - ".$i."\">";
                        $i++;
                    }
                }
                closedir($dir);
            $code .= "</div></div>";
        if ($stloc == "ru") {
            $code .= "<div class=\"sl_drop\"><span OnClick=\"HideShow('l-form-".$id."', 'blind', 'up', 500); changelanguage();\" class=\"sl_bb_translate\" title=\""._EAUTOTR."\"></span>
            <div id=\"l-form-".$id."\" class=\"sl_drop-form\">
                <table class=\"sl_bb_trans\"><tr>
                <td>А</td><td>Б</td><td>В</td><td>Г</td><td>Д</td><td>Е</td><td>Ё</td><td>Ж</td><td>З</td><td>И</td><td>Й</td>
                <td>К</td><td>Л</td><td>М</td><td>Н</td><td>О</td><td>П</td><td>Р</td><td>С</td><td>Т</td><td>У</td><td>Ф</td>
                <td>Х</td><td>Ц</td><td>Ч</td><td>Ш</td><td>Щ</td><td>Ъ</td><td>Ы</td><td>Ь</td><td>Э</td><td>Ю</td><td>Я</td>
                </tr><tr>
                <td>A</td><td>B</td><td>V</td><td>G</td><td>D</td><td>E</td><td>JO</td><td>ZH</td><td>Z</td><td>I</td><td>J</td>
                <td>K</td><td>L</td><td>M</td><td>N</td><td>O</td><td>P</td><td>R</td><td>S</td><td>T</td><td>U</td><td>F</td>
                <td>X</td><td>C</td><td>CH</td><td>SH</td><td>W</td><td>'</td><td>Y</td><td>#</td><td>JE</td><td>JU</td><td>JA</td>
                </tr></table>
            </div></div>
            <span OnClick=\"translateAlltoCyrillic()\" class=\"sl_bb_translit\" title=\""._ERUS."\"></span>
            <span OnClick=\"translateAlltoLatin()\" class=\"sl_bb_trans\" title=\""._ELAT."\"></span>";
        }
        $fonts = "<option value=\"\">"._FONT."</option>";
        $font = ["Arial", "Courier", "Mistral", "Impact", "Sans Serif", "Tahoma", "Helvetica", "Verdana"];
        foreach ($font as $val) if ($val != "") $fonts .= "<option style=\"font-family: ".$val.";\" value=\"".$val."\">".$val."</option>";

        $colors = "<option value=\"\">"._ECOLOR."</option>";
        $color = ["black", "gray", "silver", "white", "maroon", "red", "orangered", "orange", "yellow", "purple", "fuchsia", "violet", "darkgreen", "green", "lime", "navy", "blue", "teal", "aqua"];
        foreach ($color as $val) if ($val != "") $colors .= "<option style=\"background: ".$val.";\" value=\"".$val."\">".$val."</option>";

        $fsizes = "<option value=\"\">"._ESIZE."</option>";
        $fsize = ["8", "10", "12", "14", "16", "18", "20", "22", "24", "26", "28", "30", "32"];
        foreach ($fsize as $val) if ($val != "") $fsizes .= "<option value=\"".$val."\">".$val."</option>";

        $fcodes = "<option value=\"\">"._CODE."</option>";
        $fcode = ["Bash", "Cpp", "CSharp", "Css", "Delphi", "Diff", "Groovy", "Java", "JScript", "Php", "Plain", "Python", "Ruby", "Scala", "Sql", "Vb", "Xml"];
        foreach ($fcode as $val) if ($val != "") $fcodes .= "<option value=\"".strtolower($val)."\">".$val."</option>";

        $code .= "<div class=\"sl_drop\">
            <span OnClick=\"HideShow('t-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_text\" title=\""._TEXT."\"></span>
            <div id=\"t-form-".$id."\" class=\"sl_drop-form\">
                <ul>
                    <li><select name=\"family\" OnChange=\"InsertCode('family', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fonts."</select></li>
                    <li><select name=\"color\" OnChange=\"InsertCode('color', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$colors."</select></li>
                    <li><select name=\"size\" OnChange=\"InsertCode('size', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fsizes."</select></li>
                </ul>
            </div>
        </div>
        <div class=\"sl_drop\">
            <span OnClick=\"HideShow('c-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_code\" title=\""._CODE."\"></span>
            <div id=\"c-form-".$id."\" class=\"sl_drop-form\"><ul><li><select name=\"code\" OnChange=\"InsertCode('code', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fcodes."</select></li></ul></div>
        </div>";
        if (is_admin()) {
            $code .= "<div class=\"sl_bb_sep\"></div>"
            ."<span OnClick=\"InsertCode('usehtml', '', '', '', '".$id."')\" class=\"sl_bb_html\" title=\""._EUSEHTML."\"></span>"
            ."<span OnClick=\"InsertCode('usephp', '', '', '', '".$id."')\" class=\"sl_bb_php\" title=\""._EUSEPHP."\"></span>";
            $conf['name'] = (!empty($conf['name'])) ? $conf['name'] : "";
            if ($op == "faq_add" || $op == "news_add" || $op == "page_add" || $conf['name'] == "faq" || $conf['name'] == "news" || $conf['name'] == "page") $code .= "<span OnClick=\"InsertCode('pagebreak', '', '', '', '".$id."')\" class=\"sl_bb_break\" title=\""._EBREAK."\"></span>";
        }
        $code .= "</div>";
        if ((defined("ADMIN_FILE") && $con[10] == 1) || (is_user() && $con[10] == 1) || (!is_user() && $con[11] == 1)) {
            $code .= "<div id=\"af-form-".$id."\" class=\"sl_bbup-panel sl_none\">";
            if ($id == 1) {
                $uinfo = '<div class="ico sl_info sl_left"><b>'._UPLOADINFO.'</b><br>'._FTYPE.': '.str_replace(',', ', ', $con[0]).'<br>'._FSIZEALL.': '.files_size($con[1]).'<br>'._FSIZE.': '.files_size($con[2]).'<br>'._AWIDTH.': '.$con[3].' px<br>'._AHEIGHT.': '.$con[4].' px<br>'._FILEUP.': '.$con[5].'<br>'.'</div>';
                $code .= "<script>
                $(document).ready(function(e) {
                    $('#msg').html('".$uinfo."');
                    $('#file_upload').on('change', function () {
                        var form_data = new FormData();
                        var ins = document.getElementById('file_upload').files.length;
                        for (var x = 0; x < ins; x++) {
                            form_data.append('file[]', document.getElementById('file_upload').files[x]);
                        }
                        form_data.append('token', '".md5_salt($conf['sitekey'])."');
                        $.ajax({
                            url: 'index.php?go=4&mod=".$mod."&userid=".intval($user[0])."',
                            type: 'POST',
                            dataType: 'text',
                            data: form_data,
                            cache: false,
                            contentType: false,
                            processData: false,
                            beforeSend: function() {
                                $('#msg').html('<div class=\"sl_loading\"></div><br>');
                            },
                            success: function (response) {
                                console.log('Success: ', response);
                                $('#msg').html(response);
                                AjaxLoad('GET', '1', 'f".$id."', 'go=1&op=show_files&id=".$id."&dir=".$mod."', '');
                            },
                            error: function (response) {
                                console.log('Error: ', response);
                                $('#msg').html(response);
                                alert('File upload error!');
                            }
                        });
                    });
                });
                </script>
                <div id=\"msg\"></div>
                <div class=\"sl_pos_center\">
                <input type=\"file\" id=\"file_upload\" name=\"file[]\" multiple=\"multiple\" class=\"sl_field\">
                <input type=\"button\" value=\""._UPDATE."\" OnClick=\"AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id."&amp;dir=".$mod."', ''); return false;\" class=\"sl_but_green\"></div>";
            } else {
                $code .= "<div class=\"sl_pos_center\"><input type=\"button\" value=\""._UPDATE."\" OnClick=\"AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id."&amp;dir=".$mod."', ''); return false;\" class=\"sl_but_green\"></div>";
            }
            $code .= "<div id=\"repf".$id."\" style=\"margin: 5px;\"></div></div>";
        }
        $code .= "</div></td></tr></table>";
    } elseif ((defined('ADMIN_FILE') && $editor == 2) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 2)) {
        static $jscript;
        if (defined('ADMIN_FILE') && $editor == 2) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/tinymce/tinymce.min.js"></script>
                <script>
                tinymce.init({
                    selector: "textarea",
                    theme: "modern",
                    plugins: [
                        "advlist autolink lists link image charmap print preview hr anchor pagebreak",
                        "searchreplace wordcount visualblocks visualchars code fullscreen",
                        "insertdatetime media nonbreaking save table contextmenu directionality",
                        "emoticons template paste textcolor responsivefilemanager"
                    ],
                    toolbar1: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
                    toolbar2: "responsivefilemanager print preview media | forecolor backcolor emoticons",
                    image_advtab: true,
                    templates: [
                        { title: "Test template 1", content: "Test 1" },
                        { title: "Test template 2", content: "Test 2" }
                    ],
                    language: "'.$stloc.'",
                    external_filemanager_path: "../plugins/filemanager/",
                    filemanager_title: "'._EUPLOAD.'" ,
                    external_plugins: { "filemanager" : "../filemanager/plugin.min.js" }
                });
                </script>';
                $jscript = 1;
            } else {
                $code = '';
            }
        } elseif (!defined("ADMIN_FILE") && $conf['redaktor'] == 2) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/tinymce/tinymce.min.js"></script>
                <script>
                tinymce.init({
                    selector: "textarea",
                    plugins: [
                        "advlist autolink lists link image charmap print preview anchor",
                        "searchreplace visualblocks code fullscreen",
                        "insertdatetime media table contextmenu paste"
                    ],
                    toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
                    language: "'.$stloc.'"
                });
                </script>';
                $jscript = 1;
            } else {
                $code = '';
            }
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.'>'.$desc.'</textarea>';
    } elseif ((defined('ADMIN_FILE') && $editor == 3) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 3)) {
        if (defined('ADMIN_FILE') && $editor == 3) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/ckeditor/ckeditor.js"></script><script src="plugins/ckeditor/adapters/jquery.js"></script>';
                $jscript = 1;
            } else {
                $code = '';
            }
            $code .= "<script>
            $(document).ready( function() {
                $('textarea#".$id."').ckeditor({
                    language: '".$stloc."',
                    filebrowserBrowseUrl: '../plugins/filemanager/dialog.php?type=2&editor=ckeditor&fldr=',
                    filebrowserUploadUrl: '../plugins/filemanager/dialog.php?type=2&editor=ckeditor&fldr=',
                    filebrowserImageBrowseUrl: '../plugins/filemanager/dialog.php?type=1&editor=ckeditor&fldr='
                });
            });
            </script>";
        } elseif (!defined('ADMIN_FILE') && $conf['redaktor'] == 3) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/ckeditor/ckeditor.js"></script><script src="plugins/ckeditor/adapters/jquery.js"></script>';
                $jscript = 1;
            } else {
                $code = '';
            }
            $code .= "<script>
            $(document).ready( function() {
                $('textarea#".$id."').ckeditor({
                    language: '".$stloc."'
                });
            });
            </script>";
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.'>'.$desc.'</textarea>';
    } elseif (defined('ADMIN_FILE') && $editor == 4) {
        if (!isset($jscript)) {
            $code = '<script src="plugins/codemirror/lib/codemirror.js"></script>
            <script src="plugins/codemirror/addon/edit/matchbrackets.js"></script>

            <script src="plugins/codemirror/addon/hint/show-hint.js"></script>
            <script src="plugins/codemirror/addon/hint/xml-hint.js"></script>
            <script src="plugins/codemirror/addon/hint/html-hint.js"></script>

            <script src="plugins/codemirror/mode/htmlmixed/htmlmixed.js"></script>
            <script src="plugins/codemirror/mode/xml/xml.js"></script>
            <script src="plugins/codemirror/mode/javascript/javascript.js"></script>
            <script src="plugins/codemirror/mode/css/css.js"></script>';
            $jscript = 1;
        } else {
            $code = '';
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" class="'.$style.'"'.$placeholder.'>'.str_replace('&amp;', '&amp;amp;', $desc).'</textarea>
        <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("'.$id.'"), {
            lineNumbers: true,
            matchBrackets: true,
            mode: "text/html",
            extraKeys: {"Ctrl": "autocomplete"},
            value: document.documentElement.innerHTML,
            indentUnit: 4,
            indentWithTabs: true
        });
        </script>';
    } else {
        $code = '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.$required.'>'.str_replace('&amp;', '&amp;amp;', $desc).'</textarea>';
    }
    return $code;
}

# Format ajax edit
function textareae(mixed $obj, mixed $go, mixed $op, mixed $id, mixed $cid, mixed $typ, mixed $mod, mixed $text, int $rows): string {
 global $conf, $admin;
    $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
    $desc = ((defined("ADMIN_FILE") && $editor == 1) || (!defined("ADMIN_FILE") && $conf['redaktor'] == 1)) ? replace_break($text) : $text;
    $code = "<form name=\"textareae\" id=\"form".$obj."\" method=\"post\">
    <textarea id=\"text\" name=\"text\" cols=\"65\" rows=\"".$rows."\" class=\"sl_earea\">".$desc."</textarea>
    <input type=\"submit\" OnClick=\"AjaxLoad('POST', '1', '".$obj."', 'go=".$go."&amp;op=".$op."&amp;id=".$id."&amp;cid=".$cid."&amp;typ=".$typ."&amp;mod=".$mod."', { 'text':'"._CERROR1."' }); return false;\" value=\""._SAVE."\" title=\""._SAVE."\" class=\"sl_but_green\">
    <input type=\"submit\" OnClick=\"AjaxLoad('GET', '1', '".$obj."', 'go=".$go."&amp;op=".$op."&amp;id=".$id."&amp;cid=".$cid."&amp;typ=".$typ."&amp;mod=".$mod."', ''); return false;\" value=\""._BACK."\" title=\""._BACK."\" class=\"sl_but_blue\">
    </form>";
    return $code;
}

# Format code edit
function textarea_code(string $id, string $name, string $style, string $mode, string $text): string {
    static $jscript;
    if (!isset($jscript)) {
        $code = '<script src="plugins/codemirror/lib/codemirror.js"></script>
        <script src="plugins/codemirror/addon/edit/matchbrackets.js"></script>

        <script src="plugins/codemirror/addon/hint/show-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/xml-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/html-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/css-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/sql-hint.js"></script>

        <script src="plugins/codemirror/mode/htmlmixed/htmlmixed.js"></script>
        <script src="plugins/codemirror/mode/xml/xml.js"></script>
        <script src="plugins/codemirror/mode/javascript/javascript.js"></script>
        <script src="plugins/codemirror/mode/css/css.js"></script>
        <script src="plugins/codemirror/mode/clike/clike.js"></script>
        <script src="plugins/codemirror/mode/php/php.js"></script>
        <script src="plugins/codemirror/mode/sql/sql.js"></script>
        <script src="plugins/codemirror/mode/http/http.js"></script>';
        $jscript = 1;
    } else {
        $code = '';
    }
    $style = ($style) ? ' '.$style : '';
    $code .= '<textarea id="'.$id.'" name="'.$name.'" class="sl_field'.$style.'">'.$text.'</textarea>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("'.$id.'"), {
            lineNumbers: true,
            matchBrackets: true,
            mode: "'.$mode.'",
            extraKeys: {"Ctrl": "autocomplete"},
            value: document.documentElement.innerHTML,
            indentUnit: 4,
            indentWithTabs: true
        });
    </script>';
    return $code;
}

# Format nummer page for Ajax
function num_ajax(string $tpl, int $count, int $pages, int $page, int $mnum = 8, int $num = 1, string $ld = '', int $go = 0, string $op = '', int $id = 0, int $cid = 0, string $typ = '', string $mod = ''): string {
 global $afile;
    $nnum = $mnum + 1;
    if ($pages > 1) {
        $cont = "";
        if ($num > 1) {
            $prev = $num - 1;
            $cprev = "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$prev."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" class=\"sl_num\" title=\""._BACK."\">"._BACK."</a>";
        } else {
            $cprev = "<span class=\"sl_num\" title=\""._BACK."\">"._BACK."</span>";
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= "<span title=\"".$i."\">".$i."</span>";
            } else {
                if ((($i > ($num - $mnum)) && ($i < ($num + $mnum))) || ($i == $pages) || ($i == 1)) $cont .= "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$i."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" title=\"".$i."\">".$i."</a>";
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $mnum))) $cont .= " ";
                if (($num > $nnum) && ($i == 1)) $cont .= "<span class=\"sl_num_exit\" title=\"&hellip;\">&hellip;</span>";
                if (($num < ($pages - $mnum)) && ($i == ($pages - 1))) $cont .= "<span class=\"sl_num_exit\" title=\"&hellip;\">&hellip;</span>";
            }
        }
        if ($num < $pages) {
            $next = $num + 1;
            $cnext = " <a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$next."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" class=\"sl_num\" title=\""._NEXT."\">"._NEXT."</a>";
        } else {
            $cnext = "<span class=\"sl_num\" title=\""._NEXT."\">"._NEXT."</span>";
        }
        return setTemplateBasic($tpl, ['{%overall%}' => _OVERALL, '{%count%}' => $count, '{%by%}' => _BY, '{%pages%}' => $pages, '{%page_s%}' => _PAGE_S, '{%page%}' => $page, '{%perpage%}' => _PERPAGE, '{%pager%}' => $cont, '{%prev%}' => $cprev, '{%next%}' => $cnext]);
    }
    return '';
}

# Check type upload file
function check_file(string $type, string $typefile): ?string {
    $strtypefile = str_replace(",", "|", $typefile);
    if (!preg_match("#".$strtypefile."#i", $type) || preg_match("#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i", $type)) return _ERROR_FILE;
}

# Check size upload file
function check_size(string $file, int $width, int $height): ?string {
    list($imgwidth, $imgheight) = getimagesize($file);
    if ($imgwidth > $width || $imgheight > $height) return _ERROR_SIZE;
}

# Crypted md5 and salt
function md5_salt(string $pass): string {
 global $conf;
    $crypt = md5(md5($conf['lic_f']).md5($pass));
    return $crypt;
}

# Upload file
function upload(int $typ, string $directory, string $typefile, int $maxsize, string $namefile, int $width, int $height, string $userid = '', string $url = ''): mixed {
 global $user, $conf, $stop;
    if ($typ == 1 && !empty($_FILES['userfile']['size'])) {
        if (is_uploaded_file($_FILES['userfile']['tmp_name'])) {
            if ($_FILES['userfile']['size'] > $maxsize) {
                $stop = _ERROR_BIG;
                return 0;
            } else {
                $type = strtolower(substr(strrchr($_FILES['userfile']['name'], '.'), 1));
                if (!check_file($type, $typefile) && !check_size($_FILES['userfile']['tmp_name'], $width, $height)) {
                    if (is_admin() && !is_user()) {
                        $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                    } else {
                        $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                        $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                    }
                    if (file_exists($directory.'/'.$newname)) {
                        $stop = _ERROR_EXIST;
                        return 0;
                    } else {
                        $res = copy($_FILES['userfile']['tmp_name'], $directory.'/'.$newname);
                        if (!$res) {
                            $stop = _ERROR_UP;
                            return 0;
                        } else {
                            return $newname;
                        }
                    }
                } else {
                    $stop = (!check_file($type, $typefile)) ? check_size($_FILES['userfile']['tmp_name'], $width, $height) : check_file($type, $typefile);
                    return 0;
                }
            }
        } else {
            $stop = _ERROR_DOWN;
            return 0;
        }
    } elseif ($typ == 2) {
        if (isset($_FILES['file']) && !empty($_FILES['file']) && getVar('post', 'token', 'raw', '') == md5_salt($conf['sitekey'])) {
            $files = count($_FILES['file']['name']);
            for ($i = 0; $i < $files; $i++) {
                if ($_FILES['file']['size'][$i] > $maxsize) {
                    echo '<div class="ico sl_warn">'._ERROR_BIG.'</div>';
                } else {
                    $type = strtolower(substr(strrchr($_FILES['file']['name'][$i], '.'), 1));
                    if (!check_file($type, $typefile) && !check_size($_FILES['file']['tmp_name'][$i], $width, $height)) {
                        if (is_admin() && !is_user()) {
                            $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                        } else {
                            $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                            $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                        }
                        if (file_exists($directory.'/'.$newname)) {
                            echo '<div class=" ico sl_warn">'._ERROR_EXIST.'</div>';
                        } else {
                            $res = copy($_FILES['file']['tmp_name'][$i], $directory.'/'.$newname);
                            if (!$res) {
                                echo '<div class="ico sl_warn">'._ERROR_UP.'</div>';
                            } else {
                                echo '<div class="ico sl_info">'._FILE_RENAMED.': '.$newname.'</div>';
                            }
                        }
                    } else {
                        $info = (!check_file($type, $typefile)) ? check_size($_FILES['file']['tmp_name'][$i], $width, $height) : check_file($type, $typefile);
                        echo '<div class="ico sl_warn">'.$info.'</div>';
                    }
                }
            }
        } else {
            echo '<div class="ico sl_warn">'._ERROR_DOWN.'</div>';
        }
    } elseif ($typ == 3 && !empty(getVar('post', 'sitefile', 'raw', ''))) {
        $sitefile = getVar('post', 'sitefile', 'raw', '');
        $afile = str_replace(['&', '?', '#'], '', $sitefile);
        $type = strtolower(substr(strrchr($afile, '.'), 1));
        if (!check_file($type, $typefile) && !check_size($sitefile, $width, $height)) {
            $fn = $sitefile;
            $path_sitefile = fopen($fn, 'rb');
            if (!$path_sitefile) {
                $stop = _ERROR_DOWN;
                return 0;
            } else {
                if (is_admin() && !is_user()) {
                    $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                } else {
                    $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                    $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                }
                $dir = $directory.'/'.$newname;
                if (file_exists($dir)) {
                    $stop = _ERROR_EXIST;
                    return 0;
                } else {
                    while (!feof($path_sitefile)) $data .= fread($path_sitefile, 1024);
                    fclose($path_sitefile);
                    $path_sitefile = fopen($directory.'/'.$newname, 'wb');
                    if (!$path_sitefile) {
                        $stop = _ERROR_UP;
                        return 0;
                    } else {
                        fwrite($path_sitefile, $data);
                        fclose($path_sitefile);
                        if (file_exists($dir)) {
                            if (filesize($dir) > $maxsize) {
                                unlink($dir);
                                $stop = _ERROR_BIG;
                                return 0;
                            } else {
                                return $newname;
                            }
                        }
                    }
                }
            }
        } else {
            $stop = (!check_file($type, $typefile)) ? check_size($sitefile, $width, $height) : check_file($type, $typefile);
            return 0;
        }
    } elseif ($typ == 4 && $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        if (!$result) return 0;
        preg_match('#Content-Type: \w+(\/)(?<value>\w+)#', $result, $value);
        $type = ($value['value'] == 'jpeg') ? 'jpg' : $value['value'];
        if (is_admin() && !is_user()) {
            $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
        } else {
            $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
            $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
        }
        $dir = $directory.'/'.$newname;
        $from = file_get_contents($url);
        file_put_contents($dir, $from);
        return $newname;
    }
}

# Format language
function language(string $lang = '', string $typ = ''): string {
    $dir = opendir("language");
    $cont = (!$typ) ? "<option value=\"\">"._ALL."</option>" : "";
    while (false !== ($file = readdir($dir))) {
        if (preg_match("#^(.+)\.php#", $file, $matches)) {
            $langf = $matches[1];
            $title = deflang($langf);
            $sel = ($lang == $langf) ? " selected" : "";
            $cont .= "<option value=\"".$langf."\"".$sel.">".$title."</option>";
        }
    }
    closedir($dir);
    return $cont;
}

# Format module
function modul(string $name, string $class, string $modul, string $no = ''): string {
    $class = ($class) ? ' class="'.$class.'"' : '';
    $content = '<select name="'.$name.'[]"'.$class.' multiple>';
    if (!empty($no)) {
        $sel = empty($modul) ? ' selected' : '';
        $content .= '<option value="0"'.$sel.'>'._NO.'</option>';
    }
    $modul = explode(',', $modul);
    $dir = opendir('modules');
    while (false !== ($file = readdir($dir))) {
        if (!preg_match('#\.#', $file)) {
            foreach ($modul as $val) {
                if ($val != '' && $val == $file) {
                    $sel = ' selected';
                    break;
                } else {
                    $sel = '';
                }
            }
            $content .= '<option value="'.$file.'"'.$sel.'>'.deflmconst($file).'</option>';
        }
    }
    closedir($dir);
    $content .= '</select>';
    return $content;
}

# Format categorie module
function cat_modul(string $selectName, string $extraClass = '', string $selected = '', bool $autoSubmit = false): string {
    $submit  = $autoSubmit ? ' OnChange="submit()"' : '';
    $class   = $extraClass ? ' class="'.$extraClass.'"' : '';
    $content = "<select name=\"".$selectName."\"".$class.$submit.">";
    $mods = ["faq", "files", "forum", "help", "jokes", "links", "media", "news", "pages", "shop"];
    foreach ($mods as $m) {
        $sel     = ($selected == $m) ? " selected" : "";
        $content .= "<option value=\"".$m."\"".$sel.">".deflmconst($m)." - ".$m."</option>";
    }
    $content .= "</select>";
    return $content;
}

# Format editor
function redaktor(int $id, string $name, string $class, int $editor, mixed $submit): string {
 global $conf;
    $submit = ($submit) ? ' OnChange="submit()"' : '';
    $class = ($class) ? ' class="'.$class.'"' : '';
    $content = '<select name="'.$name.'"'.$submit.$class.'>';
    $ename = ($id == 1) ? [0 => _NO, 1 => 'SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => 'TinyMCE 4.5.6', 3 => 'CKEditor 4.6.2', 4 => 'CodeMirror 5.25.0'] : [0 => _NO, 1 => 'SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => 'TinyMCE 4.5.6', 3 => 'CKEditor 4.6.2'];
    foreach ($ename as $key => $value) {
        $sel = ($editor == $key) ? ' selected' : '';
        if ($key <= 1) {
            $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 2) {
            if (file_exists('plugins/tinymce/')) $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 3) {
            if (file_exists('plugins/ckeditor/')) $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 4) {
            $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        }
    }
    $content .= '</select>';
    return $content;
}

# Show comments
function ashowcom(int $cid = 0, string $mod = ''): string {
 global $db, $conf, $afile, $user;
    $mod = analyze($mod);
    $params = [];
    if (defined("ADMIN_FILE")) {
        if (getVar('get', 'status', 'num', 0) == 1) {
            $ordern = "WHERE status = :status";
            $params = ['status' => 0];
        } else {
            $ordern = "WHERE status != :status";
            $params = ['status' => 0];
        }
        $ccnum = $conf['comments']['anum'];
        $plnum = $conf['comments']['anump'];
    } else {
        if (is_moder($mod)) {
            $ordern = "WHERE cid = :cid AND modul = :mod";
            $params = ['cid' => $cid, 'mod' => $mod];
        } else {
            $ordern = "WHERE cid = :cid AND modul = :mod AND status != :status";
            $params = ['cid' => $cid, 'mod' => $mod, 'status' => 0];
        }
        $ccnum = $conf['comments']['num'];
        $plnum = $conf['comments']['nump'];
    }
    list($numstories) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(cid) FROM ".PREFIX_DB."_comment ".$ordern, $params));
    if ($numstories > 0) {
        $com = getVar('get', 'com', 'num', '1');
        $offset = ($com - 1) * $ccnum;
        $numpages = ceil($numstories / $ccnum);
        if ($conf['comments']['sort']) {
            $sort = "ASC";
            $a = ($com) ? $offset+1 : 1;
        } else {
            $sort = "DESC";
            $a = $numstories;
            if ($numstories > $offset) $a -= $offset;
        }
        $where = [];
        $result = $db->sql_query("SELECT id, cid, modul, date, uid, name, host_name, comment, status FROM ".PREFIX_DB."_comment ".$ordern." ORDER BY date ".$sort." LIMIT ".intval($offset).", ".intval($ccnum), $params);
        while (list($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status) = $db->sql_fetchrow($result)) {
            $cmassiv[] = [$com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status];
            if ($com_uid) $where[] = $com_uid;
            unset($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status);
        }
        if ($where) {
            $uids = array_values(array_unique(array_map('intval', $where)));
            $uids = array_values(array_filter($uids, static fn($v) => $v > 0));
            if ($uids) {
                $up = [];
                $um = [];
                foreach ($uids as $k => $v) {
                    $ph = 'u'.$k;
                    $up[] = ':'.$ph;
                    $um[$ph] = $v;
                }
                $result2 = $db->sql_query("SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_from, u.user_sig, u.user_viewemail, u.user_points, u.user_warnings, u.user_gender, u.user_votes, u.user_totalvotes, g.name, g.rank, g.color FROM ".PREFIX_DB."_users AS u LEFT JOIN ".PREFIX_DB."_groups AS g ON ((g.extra = 1 AND u.user_group = g.id) OR (g.extra != 1 AND u.user_points >= g.points)) WHERE u.user_id IN (".implode(', ', $up).") ORDER BY g.extra ASC, g.points ASC", $um);
                while (list($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor) = $db->sql_fetchrow($result2)) {
                    $umassiv[] = [$user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor];
                    unset($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
                }
            }
        }
        $cont = "";
        if (defined("ADMIN_FILE")) {
            $cont .= "<form name=\"comm\" action=\"".$afile.".php\" method=\"post\">";
            $b = 0;
        }
        foreach ($cmassiv as $val) {
            $com_id = $val[0];
            $com_cid = $val[1];
            $com_modul = $val[2];
            $com_date = $val[3];
            $com_uid = $val[4];
            $com_name = $val[5];
            $com_host = $val[6];
            $com_text = $val[7];
            $com_status = $val[8];
            unset($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
            if (isset($umassiv)) {
                foreach ($umassiv as $val2) {
                    if (strtolower($com_uid) == strtolower($val2[0])) {
                        $user_id = $val2[0];
                        $user_name = $val2[1];
                        $user_rank = $val2[2];
                        $user_email = $val2[3];
                        $user_website = $val2[4];
                        $user_avatar = $val2[5];
                        $user_regdate = $val2[6];
                        $user_from = $val2[7];
                        $user_sig = $val2[8];
                        $user_viewemail = $val2[9];
                        $user_points = $val2[10];
                        $user_warnings = $val2[11];
                        $user_gender = $val2[12];
                        $user_votes = $val2[13];
                        $user_totalvotes = $val2[14];
                        $user_gname = $val2[15];
                        $user_grank = $val2[16];
                        $user_gcolor = $val2[17];
                    }
                }
            }
            $avname = (!empty($user_name)) ? $user_name : $com_name." ("._ANONYM.")";
            $date = "<span title=\""._PADD."\" class=\"sl_t_post\">".format_time($com_date, _TIMESTRING)."</span>";
            $ip = (is_moder($com_modul)) ? user_geo_ip($com_host, 4) : "";
            $amess = "<a href=\"#".$com_id."\" title=\""._COMMENT.": ".$a."\" class=\"sl_pnum\">".$a."</a>";
            $avatar = (!empty($user_name)) ? (($user_avatar && file_exists($conf['users']['adirectory']."/".$user_avatar)) ? $conf['users']['adirectory']."/".$user_avatar : $conf['users']['adirectory']."/default/00.gif") : $conf['users']['adirectory']."/default/0.gif";
            $rank = (!empty($user_rank)) ? $user_rank : "";
            $trank = (!empty($user_gname)) ? _GROUP.": ".$user_gname : _RANK;
            $rlink = (!empty($user_grank) && file_exists(img_find("ranks/".$user_grank))) ? "<img src=\"".img_find("ranks/".$user_grank)."\" alt=\"".$trank."\" title=\"".$trank."\">" : "";
            $rate = (!empty($user_id)) ? ajax_rating(0, $user_id, "account", $user_votes, $user_totalvotes, $com_id, 1) : "";
            $rwarn = (!empty($user_warnings)) ? _UWARNS.": ".warnings($user_warnings) : "";
            $group = (!empty($user_gname)) ? _GROUP.": <span style=\"color: ".$user_gcolor."\">".$user_gname."</span>" : "";
            $point = ($conf['users']['point'] && !empty($user_points)) ? _POINTS.": ".$user_points : "";
            $regdate = (!empty($user_regdate)) ? _REG.": ".format_time($user_regdate) : _NO_INFO;
            $gender = (!empty($user_gender)) ? _GENDER.": ".gender($user_gender) : "";
            $from = (!empty($user_from)) ? _FROM.": ".$user_from : "";
            $sig = (!empty($user_sig)) ? "<hr>".$user_sig : "";
            $personal = (is_moder($com_modul) || is_user() || $conf['comments']['anonpost'] != 0) ? "<a href=\"javascript: InsertCode('name', '".$avname."', '', '', '1');\" title=\""._PERSONAL."\" class=\"sl_but_blue\">"._PERS."</a>" : "";
            $privat = ($conf['comments']['privat'] && $conf['privat']['act'] && !empty($user_name)) ? "<a href=\"index.php?name=account&amp;op=privat&amp;uname=".urlencode($user_name)."\" title=\""._SENDMES."\" class=\"sl_but_green\">"._MESSAGE."</a>" : "";
            $profil = ($conf['comments']['profil'] && !empty($user_name)) ? "<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($user_name)."\" title=\""._PERSONALINFO."\" class=\"sl_but\">"._ACCOUNT."</a>" : "";
            $web = ($conf['comments']['web'] && !empty($user_website)) ? "<a href=\"".$user_website."\" target=\"_blank\" title=\""._DOWNLLINK."\" class=\"sl_but\">"._SITE."</a>" : "";

            # Future functions
            #$warn = "<a href=\"javascript: scroll(0, 0);\" title=\""._WARNM."\">"._WARNM."</a>";
            #$thank = "<a href=\"javascript: scroll(0, 0);\" title=\""._THANK."\">"._THANK."</a>";
            $warn = "";
            $thank = "";

            if (is_moder($com_modul)) {
                if (defined("ADMIN_FILE")) {
                    $edit = add_menu("<a href=\"index.php?name=".$com_modul."&amp;op=view&amp;id=".$com_cid."#".$com_id."\" title=\""._MVIEW."\">"._MVIEW."</a>||<a href=\"".$afile.".php?op=comm_edit&amp;id=".$com_id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$afile.".php?op=comm_act&amp;id=".$com_id."&amp;refer=1\" title=\""._ACTIVATE."\">"._ACTIVATE."</a>||<a href=\"".$afile.".php?op=comm_del&amp;id=".$com_id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".cutstr(text_filter(bb_decode($com_text, $com_modul)), 10)."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>");
                } else {
                    $edit = add_menu("<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=editcom&amp;id=".$com_id."&amp;typ=1&amp;mod=".$com_modul."', ''); return false;\" title=\""._ONEDIT."\">"._ONEDIT."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id."&amp;typ=0&amp;mod=".$com_modul."', ''); return false;\" title=\""._FMODC."\">"._FMODC."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id."&amp;typ=1&amp;mod=".$com_modul."', ''); return false;\" title=\""._ACTIVATE."\">"._ACTIVATE."</a>");
                }
            } else {
                $stime = strtotime($com_date) + $conf['comments']['edit'];
                $edit = (is_user() && isset($user_id) == intval($user[0]) && time() < $stime) ? add_menu("<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=editcom&amp;id=".$com_id."&amp;typ=1&amp;mod=".$com_modul."', ''); return false;\" title=\""._ONEDIT."\">"._ONEDIT."</a>") : "";
            }
            $hclass = (!defined("ADMIN_FILE") && !$com_status) ? "title=\""._PCLOSED."\" class=\"sl_hidden\"" : "";
            $text = "<div id=\"repcom".$com_id."\">".bb_decode($com_text, $com_modul)."</div>";
            if (defined("ADMIN_FILE")) {
                $checkb = (!$b) ? " "._CHECKALL." <input type=\"checkbox\" name=\"markcheck\" id=\"markcheck\" OnClick=\"CheckBox('#markcheck', '.sl_check')\"> | <input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$com_id."\">" : " <input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$com_id."\">";
                $b++;
            } else {
                $checkb = "";
            }
            $cont .= setTemplateBasic("comment", ['{%id%}' => $com_id, '{%username%}' => $avname, '{%date%}' => $date, '{%ip%}' => $ip, '{%post_count%}' => $amess, '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => $text, '{%sig%}' => bb_decode($sig, $com_modul), '{%btn_personal%}' => $personal, '{%btn_pm%}' => $privat, '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_warn%}' => $warn, '{%btn_thank%}' => $thank, '{%btn_edit%}' => $edit, '{%hclass%}' => $hclass, '{%checkb%}' => $checkb]);
            if ($conf['comments']['sort']) { $a++; } else { $a--; }
        }
        if (defined("ADMIN_FILE")) {
            $selms = _CHECKOP.": <select name=\"op\"><option value=\"comm_act\">"._ACTIVATE."</option><option value=\"comm_del\">"._DELETE."</option></select> <input type=\"hidden\" name=\"refer\" value=\"1\"><input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\">";
            $pag = (getVar('get', 'status', 'num', 0) == 1) ? "op=comm_show&amp;status=1" : "op=comm_show";
            $numpt = setPageNumbers('pagenum', $com_modul, $numstories, $numpages, $ccnum, $pag.'&amp;', $plnum, 0, '', 'com');
            $cont .= setTemplateBasic('list-bottom', ['{%pager%}' => $numpt, '{%select%}' => $selms]);
            $out = setTemplateBasic("open", []).$cont.setTemplateBasic("close", []);
        } else {
            $num = getVar('get', 'num', 'num');
            $pag = empty($num) ? 'op=view&id='.$cid : 'op=view&id='.$cid.'&num='.$num;
            $cont .= setPageNumbers('pagenum', $com_modul, $numstories, $numpages, $ccnum, $pag.'&', $plnum, 0, '#comm', 'com');
            $out = setTemplateBasic('title', ['{%title%}' => _COMMENTS]).setTemplateBasic('open').$cont.setTemplateBasic('close');
        }
    } else {
        $winfo = (defined('ADMIN_FILE')) ? _NO_INFO : _NOCOMMENTS;
        $out = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $winfo]);
    }
    return $out;
}

# Save edit comments
function editcom(): ?string {
 global $db, $conf, $user;
    $id   = getVar('post', 'id',   'num',  0) ?: getVar('get', 'id',   'num',  0);
    $typ  = getVar('post', 'typ',  'num',  0) ?: getVar('get', 'typ',  'num',  0);
    $mod  = analyze(getVar('post', 'mod',  'text', '') ?: getVar('get', 'mod',  'text', ''));
    $text = trim(getVar('post', 'text', 'raw',  '') ?: getVar('get', 'text', 'raw',  ''));
    list($uid, $date, $comment) = $db->sql_fetchrow($db->sql_query("SELECT uid, date, comment FROM ".PREFIX_DB."_comment WHERE id = :id", ['id' => $id]));
    $stime = strtotime($date) + $conf['comments']['edit'];
    if (is_moder($mod) || (is_user() && $uid == intval($user[0]) && time() < $stime)) {
        if ($id && $mod && !$text) {
            $content = ($typ) ? textareae("com".$id, "1", "editcom", $id, "0", "0", $mod, $comment, "10") : bb_decode($comment, $mod);
            echo $content;
        } elseif ($id && $mod && $text) {
            $checks = str_replace(["\n", "\r", "\t"], " ", $text);
            $e = explode(" ", $checks);
            for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
            $stop = [];
            if ($text == "") $stop[] = _CERROR1;
            if ($o > $conf['comments']['letter']) $stop[] = _CERROR2;
            if (!is_moder($mod) && (($conf['comments']['link'] == 1 && !is_user()) || ($conf['comments']['link'] == 2)) && stripos($text, "http://") !== false) $stop[] = _CERROR9;
            $urlclick = (!is_moder($mod) && (($conf['comments']['alink'] == 1 && !is_user()) || ($conf['comments']['alink'] == 2))) ? 1 : 0;
            if (!$stop) {
                $comm = save_text($text, $urlclick);
                $db->sql_query("UPDATE ".PREFIX_DB."_comment SET comment = :comment WHERE id = :id", ['comment' => $comm, 'id' => $id]);
                echo bb_decode($comm, $mod);
            } else {
                return setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
            }
        }
    } else {
        $info = sprintf(_PEDEND, intval($conf['comments']['edit'] / 60));
        return setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Close comments
function closecom(): void {
 global $db;
    $id  = getVar('post', 'id',  'num',  0) ?: getVar('get', 'id',  'num',  0);
    $typ = getVar('post', 'typ', 'num',  0) ?: getVar('get', 'typ', 'num',  0);
    $mod = analyze(getVar('post', 'mod', 'text', '') ?: getVar('get', 'mod', 'text', ''));
    if ($id && $mod && is_moder($mod)) {
        $status = ($typ) ? 1 : 0;
        $info = ($typ) ? _PCOPEN : _PCLOSED;
        $numcom = ($typ) ? 0 : 1;
        $db->sql_query("UPDATE ".PREFIX_DB."_comment SET status = :status WHERE id = :id", ['status' => $status, 'id' => $id]);
        list($cid, $uid) = $db->sql_fetchrow($db->sql_query("SELECT cid, uid FROM ".PREFIX_DB."_comment WHERE id = :id", ['id' => $id]));
        numcom($cid, $mod, $numcom, $uid);
        echo setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Number comments
function numcom(int $id = 0, string $mod = '', bool $del = false, int $uid = 0): void {
 global $db;
    $mod   = $mod ? analyze($mod) : '';
    $delta = $del ? -1 : 1;
    $point = $del ? 1 : 0;
    if ($id && $mod) {
        if ($mod == "account" || $mod == "members") {
            #$db->sql_query("UPDATE ".PREFIX_DB."_users SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(3, $uid, $point);
        } elseif ($mod == "faq") {
            $db->sql_query("UPDATE ".PREFIX_DB."_faq SET comments = comments + :delta WHERE fid = :id", ['delta' => $delta, 'id' => $id]);
            update_points(7, $uid, $point);
        } elseif ($mod == "files") {
            $db->sql_query("UPDATE ".PREFIX_DB."_files SET totalcomments = totalcomments + :delta WHERE lid = :id", ['delta' => $delta, 'id' => $id]);
            update_points(10, $uid, $point);
        } elseif ($mod == "gallery") {
            #$db->sql_query("UPDATE ".PREFIX_DB."_gallery SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(17, $uid, $point);
        } elseif ($mod == "links") {
            $db->sql_query("UPDATE ".PREFIX_DB."_links SET totalcomments = totalcomments + :delta WHERE lid = :id", ['delta' => $delta, 'id' => $id]);
            update_points(22, $uid, $point);
        } elseif ($mod == "media") {
            $db->sql_query("UPDATE ".PREFIX_DB."_media SET totalcom = totalcom + :delta WHERE id = :id", ['delta' => $delta, 'id' => $id]);
            update_points(26, $uid, $point);
        } elseif ($mod == "multimedia") {
            #$db->sql_query("UPDATE ".PREFIX_DB."_multimedia SET totalcom=totalcom".$typ." WHERE id = '".$id."'");
            update_points(29, $uid, $point);
        } elseif ($mod == "news") {
            $db->sql_query("UPDATE ".PREFIX_DB."_news SET comments = comments + :delta WHERE sid = :id", ['delta' => $delta, 'id' => $id]);
            update_points(32, $uid, $point);
        } elseif ($mod == "pages") {
            $db->sql_query("UPDATE ".PREFIX_DB."_pages SET comments = comments + :delta WHERE pid = :id", ['delta' => $delta, 'id' => $id]);
            update_points(36, $uid, $point);
        } elseif ($mod == "shop") {
            $db->sql_query("UPDATE ".PREFIX_DB."_products SET com = com + :delta WHERE id = :id", ['delta' => $delta, 'id' => $id]);
            update_points(40, $uid, $point);
        } elseif ($mod == "voting") {
            $db->sql_query("UPDATE ".PREFIX_DB."_voting SET comments = comments + :delta WHERE id = :id", ['delta' => $delta, 'id' => $id]);
            update_points(43, $uid, $point);
        }
    }
}

# Voting result save
function avoting_save(): void {
 global $db, $conf, $user, $locale;
    $id = getVar('post', 'id', 'num', 0);
    $questions = isset($_POST['questions']) && is_array($_POST['questions']) ? $_POST['questions'] : [];
    if ($conf['multilingual'] == 1) {
        $querylang = "(language = :locale OR language = '') AND date <= NOW() AND enddate >= NOW()";
        $qlang_params = ['locale' => $locale];
    } else {
        $querylang = "date <= NOW() AND enddate >= NOW()";
        $qlang_params = [];
    }
    $result = $db->sql_query("SELECT id FROM ".PREFIX_DB."_voting WHERE id = :id AND ".$querylang, array_merge(['id' => $id], $qlang_params));
    if ($db->sql_numrows($result) > 0) {
        if (!$questions) {
            $cont = setTemplateWarning('warn', ['text' => _SEROR1, 'url' => '?name=voting&amp;op=view&amp;id='.$id, 'time' => 3, 'id' => 'warn']);
        } else {
            $ip = getIp();
            $past = time() - intval($conf['voting']['voting_t']);
            $cmod = substr("voting", 0, 2)."-".$id;
            $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : "";
            $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
            $db->sql_query("DELETE FROM ".PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
            list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND host = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
            if ($cookies == $id || $num > 0) {
                $cont = setTemplateWarning('warn', ['text' => _SEROR2, 'url' => '?name=voting&amp;op=view&amp;id='.$id, 'time' => 3, 'id' => 'warn']);
            } else {
                setcookie(substr("voting", 0, 2)."-".$id, $id, time() + intval($conf['voting']['voting_t']));
                $new = time();
                $inserted = $db->sql_query("INSERT INTO ".PREFIX_DB."_rating (mid, modul, time, uid, host) VALUES (:mid, 'voting', :time, :uid, :host)", ['mid' => $id, 'time' => $new, 'uid' => $uid, 'host' => $ip]);
                if ($inserted) {
                    list($answer) = $db->sql_fetchrow($db->sql_query("SELECT answer FROM ".PREFIX_DB."_voting WHERE id = :id", ['id' => $id]));
                    $answer = explode("|", $answer);
                    for ($q = 0; $q < count($answer); $q++) {
                        if ($answer[$q] != "") {
                            foreach ($questions as $val) {
                                if ($val != "" && $val == $q + 1) {
                                    $isansw = 1;
                                    break;
                                } else {
                                    $isansw = 0;
                                }
                            }
                            $answ[] = ($isansw) ? $answer[$q] + 1 : $answer[$q];
                        }
                    }
                    $answ = implode("|", $answ);
                    $db->sql_query("UPDATE ".PREFIX_DB."_voting SET answer = :answer WHERE id = :id", ['answer' => $answ, 'id' => $id]);
                    update_points(42);
                }
                $cont = getVoting($id);
            }
        }
    } else {
        $cont = setTemplateWarning('warn', ['time' => '3', 'url' => '?name=voting', 'id' => 'warn', 'text' => _ERROR]);
    }
    echo $cont;
}

# Update points
function update_points(int $id, int $uid = 0, bool $del = false): void {
 global $db, $conf, $user;
    $uid = $uid ?: (is_user() ? intval($user[0]) : 0);
    if ($id && $uid && $conf['users']['point'] == 1) {
        $upoints = explode(",", $conf['users']['points']);
        $a       = $id - 1;
        $delta   = isset($upoints[$a]) ? intval($upoints[$a]) : 0;
        $delta   = $del ? -$delta : $delta;
        $db->sql_query("UPDATE ".PREFIX_DB."_users SET user_points = user_points + :delta WHERE user_id = :uid", ['delta' => $delta, 'uid' => $uid]);
    }
}

# Format image preview PHP GD
function create_img_gd(string $imgfile, string $imgthumb, int $newwidth): string {
    if (function_exists("imagecreate")) {
        $imginfo = getimagesize($imgfile);
        switch($imginfo[2]) {
            case 1:
            $type = IMG_GIF;
            break;
            case 2:
            $type = IMG_JPG;
            break;
            case 3:
            $type = IMG_PNG;
            break;
            case 4:
            $type = IMG_WBMP;
            break;
            default:
            return $imgfile;
            break;
        }
        switch($type) {
            case IMG_GIF:
            if (!function_exists("imagecreatefromgif")) return $imgfile;
            $srcImage = imagecreatefromgif($imgfile);
            break;
            case IMG_JPG:
            if (!function_exists("imagecreatefromjpeg")) return $imgfile;
            $srcImage = imagecreatefromjpeg($imgfile);
            break;
            case IMG_PNG:
            if(!function_exists("imagecreatefrompng")) return $imgfile;
            $srcImage = imagecreatefrompng($imgfile);
            break;
            case IMG_WBMP:
            if (!function_exists("imagecreatefromwbmp")) return $imgfile;
            $srcImage = imagecreatefromwbmp($imgfile);
            break;
            default:
            return $imgfile;
        }
        if ($srcImage) {
            $srcWidth = $imginfo[0];
            $srcHeight = $imginfo[1];
            $ratioWidth = $srcWidth / $newwidth;
            $destWidth = $newwidth;
            $destHeight = $srcHeight / $ratioWidth;
            $destImage = imagecreatetruecolor($destWidth, $destHeight);

            imagesavealpha($destImage, true);
            $iccalpha = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefill($destImage, 0, 0, $iccalpha);
            imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);

            switch($type) {
                case IMG_GIF:
                imagegif($destImage, $imgthumb);
                break;
                case IMG_JPG:
                imagejpeg($destImage, $imgthumb);
                break;
                case IMG_PNG:
                imagepng($destImage, $imgthumb);
                break;
                case IMG_WBMP:
                imagewbmp($destImage, $imgthumb);
                break;
            }
            imagedestroy($srcImage);
            imagedestroy($destImage);
            return $imgthumb;
        } else {
            return $imgfile;
        }
    } else {
        return $imgfile;
    }
}

# Format function mb_strtolower the strtolower version to support most amount of languages including russian, french and so on
if (!function_exists("mb_strtolower")) {
    function mb_strtolower($str){
        $to = ["a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "a", "a", "a", "a", "a", "a", "?", "c", "e", "e", "e", "e", "i", "i", "i", "i", "?", "n", "o", "o", "o", "o", "o", "o", "u", "u", "u", "u", "y", "ÃÂ°", "ÃÂ±", "ÃÂ²", "ÃÂ³", "ÃÂ´", "ÃÂµ", "Ã‘â€˜", "ÃÂ¶", "ÃÂ·", "ÃÂ¸", "ÃÂ¹", "ÃÂº", "ÃÂ»", "ÃÂ¼", "ÃÂ½", "ÃÂ¾", "ÃÂ¿", "Ã‘â‚¬", "Ã‘Â", "Ã‘â€š", "Ã‘Æ’", "Ã‘â€ž", "Ã‘â€¦", "Ã‘â€ ", "Ã‘â€¡", "Ã‘Ë†", "Ã‘â€°", "Ã‘Å ", "Ã‘â€¹", "Ã‘Å’", "Ã‘Â", "Ã‘Å½", "Ã‘Â"];
        $from = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "A", "A", "A", "A", "A", "A", "?", "C", "E", "E", "E", "E", "I", "I", "I", "I", "?", "N", "O", "O", "O", "O", "O", "O", "U", "U", "U", "U", "Y", "ÃÂ", "Ãâ€˜", "Ãâ€™", "Ãâ€œ", "Ãâ€", "Ãâ€¢", "ÃÂ", "Ãâ€“", "Ã—", "ÃËœ", "Ãâ„¢", "ÃÅ¡", "Ãâ€º", "ÃÅ“", "ÃÂ", "ÃÅ¾", "ÃÅ¸", "ÃÂ ", "ÃÂ¡", "ÃÂ¢", "ÃÂ£", "ÃÂ¤", "ÃÂ¥", "ÃÂ¦", "ÃÂ§", "ÃÂ¨", "ÃÂ©", "ÃÂª", "ÃÂª", "ÃÂ¬", "ÃÂ­", "ÃÂ®", "ÃÂ¯"];
        return str_replace($from, $to, $str);
    }
}

# Format function fputcsv for PHP 4
if (!function_exists("fputcsv")) {
    function fputcsv(&$handle, $fields = [], $delimiter = ',', $enclosure = '"') {
        $str = '';
        $escape_char = '\\';
        foreach ($fields as $value) {
            if (strpos($value, $delimiter) !== false || strpos($value, $enclosure) !== false || strpos($value, "\n") !== false || strpos($value, "\r") !== false || strpos($value, "\t") !== false || strpos($value, ' ') !== false) {
                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);
                for ($i=0; $i < $len; $i++) {
                    if ($value[$i] == $escape_char) {
                        $escaped = 1;
                    } elseif (!$escaped && $value[$i] == $enclosure) {
                        $str2 .= $enclosure;
                    } else {
                        $escaped = 0;
                    }
                    $str2 .= $value[$i];
                }
                $str2 .= $enclosure;
                $str .= $str2.$delimiter;
            } else {
                $str .= $value.$delimiter;
            }
        }
        $str = substr($str,0,-1);
        $str .= "\n";
        return fwrite($handle, $str);
    }
}
