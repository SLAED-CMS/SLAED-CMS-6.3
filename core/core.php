<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

define('BLOCK_FILE', true);
define('FUNC_FILE', true);

# Base directory of the project
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));

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

# Security file include
require_once BASE_DIR.'/core/security.php';

# Comments config file include
require_once CONFIG_DIR.'/comments.php';

# Favorites config file include
require_once CONFIG_DIR.'/favorites.php';

# Private messages config file include
require_once CONFIG_DIR.'/config_privat.php';

# Voting config file include
require_once CONFIG_DIR.'/config_voting.php';

if (defined('MODULE_FILE')) {
    if (file_exists(CONFIG_DIR.'/config_core.php')) require_once CONFIG_DIR.'/config_core.php';
    include('core/user.php');
} elseif (defined('ADMIN_FILE')) {
    require_once BASE_DIR.'/core/admin.php';
}

require_once CONFIG_DIR.'/config_ratings.php';
require_once CONFIG_DIR.'/config_replace.php';
require_once CONFIG_DIR.'/config_referers.php';

### The beginning of new functions

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
    if ($comp !== 'none') return addCompress(dirname($file), $file, basename($file), $comp, filesize($file) > $max || $del);
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
function addCompress(string $dir, string $src, string $name, string $mode = 'auto', bool $del = false): bool {
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
            // Validate that $src is a string
            if (!is_string($src)) {
                $zip->close();
                addErrorFile(_ERR_TYPE.': '.gettype($src));
                return false;
            }
            
            $tmp = tempnam(sys_get_temp_dir(), 'zip_');
            if ($tmp === false) {
                $zip->close();
                addErrorFile(_ERR_WRITE.': Temp file creation failed');
                return false;
            }
            
            if (file_put_contents($tmp, $src) === false) {
                $zip->close();
                if (file_exists($tmp)) unlink($tmp);
                addErrorFile(_ERR_WRITE.': '.$tmp);
                return false;
            }
            
            // Use logical internal name
            $iname = $nbase.'.txt';
            if (!$zip->addFile($tmp, $iname)) {
                $zip->close();
                unlink($tmp);
                addErrorFile(_ERR_ZADD.': '.$tmp);
                return false;
            }
            
            unlink($tmp);
        }
        
        $zip->close();
        
        // Delete source if requested
        if ($del) {
            if (is_file($src)) {
                unlink($src);
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
        unlink($src);
    }
    
    return true;
}

/**
 * Error logging with rotation and compression
 */
function addErrorFile(string $msg): bool {
    $log = LOGS_DIR.'/error_file.log';
    $max = 10485760;

    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    if (file_put_contents($log, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[LOG] Write failed: '.$log.' | '.$msg);
        return false;
    }

    if (filesize($log) >= $max) {
        $ts = date('Ymd_His');
        $rot = $log.'.'.$ts;
        $cmp = checkCompress();
        $typ = array_key_first(array_intersect_key(['zip' => 1, 'gz' => 1, 'bz2' => 1], $cmp));
        if ($typ) {
            addCompress(dirname($rot), $log, basename($rot), $typ, true);
        } else {
            rename($log, $rot.'.bak');
        }
    }
    return true;
}


# Captcha check
function checkCaptcha($id) {
    global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $res = getVar('post', 'recaptcha', 'text');
        if ($res) {
            $url = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$conf['capsec'].'&response='.$res.'&remoteip='.getIp());
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
function isArray($arr) {
    if (is_array($arr)) {
        foreach ($arr as $a) {
            if (isArray($a)) return true;
        }
    } elseif (empty($arr)) {
        return false;
    } else {
        return true;
    }
}

# Is number positive integer
function isInt($num) {
    $inum = (int)$num;
    return ($inum == $num && is_int($inum) && $num > 0) ? true : false;
}

# Conformity check
function isCompare($a, $b) {
    return (($a && $b == '0') || (isDate($a) && isDate($b) && $a > $b)) ? 1 : 0;
}

# Checking validity of date format
function isDate($str) {
    return is_numeric(strtotime($str));
}

# Generating categories for modules
function setCategories($mod, $sub, $desc, $id='') {
    global $prefix, $db, $user, $conf, $currentlang;
    if (analyze($mod)) {
        $id = (intval($id)) ? $id : 0;
        if ($id) {
            $where = "WHERE modul = '".$mod."' AND parentid = '".$id."'";
        } elseif ($id && $conf['multilingual']) {
            $where = "WHERE modul = '".$mod."' AND parentid = '".$id."' AND (language = '".$currentlang."' OR language = '')";
        } elseif ($conf['multilingual']) {
            $where = "WHERE modul = '".$mod."' AND (language = '".$currentlang."' OR language = '')";
        } else {
            $where = "WHERE modul = '".$mod."'";
        }
        $cnum = 0;
        $result = $db->sql_query("SELECT id, title, description, img, parentid, auth_view, auth_read FROM ".$prefix."_categories ".$where." ORDER BY ordern, title");
        while (list($cid, $title, $description, $img, $parentid, $auth_view, $auth_read) = $db->sql_fetchrow($result)) {
            $massiv[] = array($cid, $title, $description, $img, $parentid, $auth_view, $auth_read);
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
                        $href = getHref(array('name='.$mod.'&cat='.$val[0], '', '', '', '', $val[1], $val[2], $val[3]));
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
                                $shref = getHref(array('name='.$mod.'&cat='.$sval[0], '', '', '', '', $sval[1], $sval[2], $sval[3]));
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
            $catid = implode(', ', $catid);
            if ($mod == 'faq') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(fid) FROM ".$prefix."_faq WHERE catid IN (".$catid.") AND time <= NOW() AND status != '0'"));
                $in = _INFA;
            } elseif ($mod == 'files') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(lid) FROM ".$prefix."_files WHERE cid IN (".$catid.") AND date <= NOW() AND status != '0'"));
                $in = _INF;
            } elseif ($mod == 'help') {
                $uid = intval($user[0]);
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(sid) FROM ".$prefix."_help WHERE catid IN (".$catid.") AND time <= NOW() AND pid = '0' AND uid = '".$uid."'"));
                $in = _INH;
            } elseif ($mod == 'jokes') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(jokeid) FROM ".$prefix."_jokes WHERE cat IN (".$catid.") AND date <= NOW() AND status != '0'"));
                $in = _INJ;
            } elseif ($mod == 'links') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(lid) FROM ".$prefix."_links WHERE cid IN (".$catid.") AND date <= NOW() AND status != '0'"));
                $in = _INL;
            } elseif ($mod == 'media') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_media WHERE cid IN (".$catid.") AND date <= NOW() AND status != '0'"));
                $in = _INM;
            } elseif ($mod == 'news') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(sid) FROM ".$prefix."_news WHERE catid IN (".$catid.") AND time <= NOW() AND status != '0'"));
                $in = _INN;
            } elseif ($mod == 'pages') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(pid) FROM ".$prefix."_pages WHERE catid IN (".$catid.") AND time <= NOW() AND status != '0'"));
                $in = _INP;
            } elseif ($mod == 'shop') {
                list($pnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_products WHERE cid IN (".$catid.") AND time <= NOW() AND active != '0'"));
                $in = _INS;
            }
            return setTemplateBasic('categories', array('{%categories%}' => _CATEGORIES, '{%content%}' => $cont, '{%total%}' => _ALLIN, '{%pages%}' => $pnum, '{%in%}' => $in, '{%cat%}' => $cnum, '{%category%}' => _ALLINC, '{%mod%}' => $mod));
        }
    }
}

# Generation of article numbers
function setArticleNumbers() {
    global $prefix, $db, $conf, $currentlang;
    $arg = func_get_args();
    if (!defined("ADMIN_FILE") && $arg[6] && $arg[7]) {
        $lwhere = ($conf['multilingual']) ? "WHERE modul = '".$arg[1]."' AND (language = '".$currentlang."' OR language = '')" : "WHERE modul = '".$arg[1]."'";
        $result = $db->sql_query("SELECT id, auth_read FROM ".$prefix."_categories ".$lwhere." ORDER BY id");
        while (list($cid, $auth_read) = $db->sql_fetchrow($result)) if (is_acess($auth_read)) $catid[] = $cid;
        $where = ($catid) ? " WHERE ".$arg[6]." IN (".implode(", ", $catid).") AND ".$arg[7] : " WHERE ".$arg[7];
    } else {
        $where = ($arg[7]) ? " WHERE ".$arg[7] : "";
    }
    list($nstories) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(".$arg[4].") FROM ".$prefix.$arg[5].$where));
    $npages = ceil($nstories / $arg[2]);
    return setPageNumbers($arg[0], $arg[1], $nstories, $npages, $arg[2], $arg[3], $arg[8], '', '', '');
}

# Generation of page numbers
function setPageNumbers() {
    global $admin_file;
    $arg = func_get_args();
    $n = !empty($arg[9]) ? $arg[9] : 'num';
    $num = !empty($arg[7]) ? intval($arg[7]) : getVar('get', $n, 'num', '1');
    $mnum = isset($arg[6]) ? $arg[6] : 8;
    $nnum = $mnum + 1;
    $anchor = isset($arg[8]) ? $arg[8] : '';
    if ($arg[3] > 1) {
        $cont = '';
        if ($num > 1) {
            $prev = $num - 1;
            $cprev = (!defined('ADMIN_FILE')) ? '<a href="'.getHref(array('name='.$arg[1].'&'.$arg[5].$n.'='.$prev, '', '', '', '', '', '', '')).$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>' : '<a href="'.$admin_file.'.php?'.$arg[5].$n.'='.$prev.$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>';
        } else {
            $cprev = '<span class="sl_num" title="'._BACK.'">'._BACK.'</span>';
        }
        for ($i = 1; $i < $arg[3]+1; $i++) {
            if ($i == $num) {
                $cont .= '<span title="'.$i.'">'.$i.'</span>';
            } else {
                if ((($i > ($num - $mnum)) && ($i < ($num + $mnum))) || ($i == $arg[3]) || ($i == 1)) $cont .= (!defined('ADMIN_FILE')) ? '<a href="'.getHref(array('name='.$arg[1].'&'.$arg[5].$n.'='.$i, '', '', '', '', '', '', '')).$anchor.'" title="'.$i.'">'.$i.'</a>' : '<a href="'.$admin_file.'.php?'.$arg[5].$n.'='.$i.$anchor.'" title="'.$i.'">'.$i.'</a>';
            }
            if ($i < $arg[3]) {
                if (($i > ($num - $nnum)) && ($i < ($num + $mnum))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
                if (($num < ($arg[3] - $mnum)) && ($i == ($arg[3] - 1))) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
            }
        }
        if ($num < $arg[3]) {
            $next = $num + 1;
            $cnext = (!defined('ADMIN_FILE')) ? '<a href="'.getHref(array('name='.$arg[1].'&'.$arg[5].$n.'='.$next, '', '', '', '', '', '', '')).$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>' : '<a href="'.$admin_file.'.php?'.$arg[5].$n.'='.$next.$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>';
        } else {
            $cnext = '<span class="sl_num" title="'._NEXT.'">'._NEXT.'</span>';
        }
        return tpl_eval($arg[0], _OVERALL, $arg[2], _BY, $arg[3], _PAGE_S, $arg[4], _PERPAGE, $cont, $cprev, $cnext);
    }
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
function setScript() {
    header('Content-type: text/javascript');
    readfile('config/cache/'.md5(get_theme().'script').'.txt');
}

# Set cached CSS file
function setCss() {
    header('Content-type: text/css');
    readfile('config/cache/'.md5(get_theme().'style').'.txt');
}

# Set bottom navigation
function setNaviLower($mod) {
    return setTemplateBasic('open').'<span class="sl_pos_center"><a href="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_foot">'._BACK.'</a><a href="index.php?name='.$mod.'" title="'._PAGEHOME.'" class="sl_but_foot">'._PAGEHOME.'</a><a OnClick="Upper(\'html, body\', 600);" title="'._PAGETOP.'" class="sl_but_foot">'._PAGETOP.'</a></span>'.setTemplateBasic('close');
}

# Theme include
function setThemeInclude() {
    global $theme;
    $theme = ($theme) ? $theme : get_theme();
    if (file_exists('templates/'.$theme.'/index.php')) include_once('templates/'.$theme.'/index.php');
    include_once('core/template.php');
}

# Load configuration file and return chmod warning if needed
function checkConfigFile(string $fp): string {
    $path = CONFIG_DIR.'/'.$fp;
    $perm = checkFileChmod($path, 666);
    return ($perm !== '') ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $perm]) : '';
}

# Check file chmod permission and try to fix it (Linux only)
function checkFileChmod(string $dir,int $chm): string {
    $out = '';
    if (file_exists($dir) && $chm > 0) {
        $per=substr(decoct(fileperms($dir)), -3);
        if (php_uname('s') === 'Linux' && $per != $chm) {
            $tdir = CONFIG_DIR.'/chmod.php';
            chmod($tdir, '0'.$chm);
            $tper = substr(decoct(fileperms($tdir)), -3);
            if ($tper == $chm) {
                chmod($dir, '0'.$chm);
                $per = substr(decoct(fileperms($dir)),-3);
            }
        }
        $out = ($per != $chm) ? $dir.' '._ERRORPERM.' CHMOD - '.$chm : '';
    }
    return $out;
}

# Saving configurations to a file
function setConfigFile(string $fp, string $name, array $arr, array $act = [], string $type = ''): void {
    $fp = CONFIG_DIR.'/'.$fp;
    if (!empty($act)) $arr += $act;
    ksort($arr);
    array_walk($arr, function (&$v): void { $v = is_bool($v) ? (string)(int)$v : (string)$v; });
    $cons = empty($type) ? 'FUNC_FILE' : 'ADMIN_FILE';
    $cnt = '<?php'.PHP_EOL
    .'# Author: Eduard Laas'.PHP_EOL
    .'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'if (!defined(\''.$cons.'\')) die(\'Illegal file access\');'.PHP_EOL.PHP_EOL
    .'$'.$name.' = '.var_export($arr, true).';';
    file_put_contents($fp, $cnt, LOCK_EX);
}

# DELETE OLD
function doConfig($fp, $name, $array, $actual='', $type='') {
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
function doScript() {
    global $theme, $conf, $confs;
    $async = ($conf['script_a']) ? 'async ' : '';
    $sfile = 'config/cache/'.md5($theme.'script').'.txt';
    $array = explode(',', $conf['script_f']);
    $array = is_array($array) ? $array : array();
    $array = (!$confs['error_java']) ? array_merge($array, array('plugins/system/block-error.js')) : $array;
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
        if (file_exists('config/config_header.php')) {
            ob_start();
            include('config/config_header.php');
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
function doCss() {
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
function doSitemap() {
    global $prefix, $db, $admin_file, $conf;
    include('config/sitemap.php');
    if (defined('ADMIN_FILE') || $confma['auto']) {
        $sess_f = 'sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = time() - intval($confma['auto_t']);
        if (defined('ADMIN_FILE') || $sess_b < $past) {
            $date = date('Y-m-d');
            $mod = empty($confma['mod'][0]) ? '0' : explode(',', $confma['mod']);
            for ($i = 0; $i < count($mod); $i++) {
                if ($mod[$i] == 'account' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT user_id, user_name, user_lastvisit FROM ".$prefix."_users");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, '', $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'content' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, title, time FROM ".$prefix."_content WHERE time <= NOW()");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, '', $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'faq' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT fid, catid, title, time FROM ".$prefix."_faq WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'files' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT lid, cid, title, date FROM ".$prefix."_files WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'forum' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, catid, title, time FROM ".$prefix."_forum WHERE pid = '0' AND time <= NOW() AND status > '1'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'jokes' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT jokeid, date, title, cat FROM ".$prefix."_jokes WHERE date <= NOW() AND status != '0'");
                    while (list($id, $time, $title, $cat) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'links' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT lid, cid, title, date FROM ".$prefix."_links WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'media' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, cid, title, subtitle, date FROM ".$prefix."_media WHERE date <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $subtitle, $time) = $db->sql_fetchrow($result)) {
                        $title = ($subtitle) ? $title.' - '.$subtitle : $title;
                        $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                    }
                } elseif ($mod[$i] == 'news' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT sid, catid, title, time FROM ".$prefix."_news WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'pages' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT pid, catid, title, time FROM ".$prefix."_pages WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'shop' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, cid, time, title FROM ".$prefix."_products WHERE time <= NOW() AND active != '0'");
                    while (list($id, $cat, $time, $title) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, $cat, $title, $time, $mod[$i]);
                } elseif ($mod[$i] == 'voting' && is_active($mod[$i], '0')) {
                    $result = $db->sql_query("SELECT id, title, date FROM ".$prefix."_voting WHERE modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')");
                    while (list($id, $title, $time) = $db->sql_fetchrow($result)) $info[$mod[$i]][] = array($id, '', $title, $time, $mod[$i]);
                } elseif (is_active($mod[$i], '0')) {
                    $info[$mod[$i]][] = array('', '', '', '', $mod[$i]);
                }
            }
            $map_h = $map_m = $map_c = $map_p = '';
            if (count($info) > 0) {
                foreach ($info as $key => $val) {
                    if ($confma['gen_m']) {
                        $map_m .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$key.'</loc>';
                        $map_m .= $confma['dat_m'] ? '<lastmod>'.$date.'</lastmod>' : '';
                        $map_m .= $confma['fr_m'] ? '<changefreq>'.$confma['fr_m'].'</changefreq>' : '';
                        $map_m .= $confma['pr_m'] ? '<priority>'.$confma['pr_m'].'</priority>' : '';
                        $map_m .= '</url>'."\n";
                    }
                    foreach ($info[$key] as $key2 => $val2) {
                        if ($confma['gen_p'] && $info[$key][$key2][0]) {
                            $map_p .= '<url><loc>'.$conf['homeurl']."/index.php?name=".$info[$key][$key2][4]."&amp;op=view&amp;id=".$info[$key][$key2][0].'</loc>';
                            $map_p .= $confma['dat_p'] ? '<lastmod>'.format_time($info[$key][$key2][3], 'Y-m-d').'</lastmod>' : '';
                            $map_p .= $confma['fr_p'] ? '<changefreq>'.$confma['fr_p'].'</changefreq>' : '';
                            $map_p .= $confma['pr_p'] ? '<priority>'.$confma['pr_p'].'</priority>' : '';
                            $map_p .= '</url>'."\n";
                        }
                        $htm[$key][$info[$key][$key2][1]][] = array($info[$key][$key2][0],$info[$key][$key2][2]);
                    }
                    $result = $db->sql_query("SELECT id, modul, title, parentid FROM ".$prefix."_categories WHERE modul = '".$key."'");
                    while (list($cid, $cmodul, $title, $parentid) = $db->sql_fetchrow($result)) {
                        $cd[$cid] = array($cid, $parentid, $title, $cmodul);
                        if ($confma['gen_c']) {
                            $map_c .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$cmodul.'&amp;cat='.$cid.'</loc>';
                            $map_c .= $confma['dat_c'] ? '<lastmod>'.$date.'</lastmod>' : '';
                            $map_c .= $confma['fr_c'] ? '<changefreq>'.$confma['fr_c'].'</changefreq>' : '';
                            $map_c .= $confma['pr_c'] ? '<priority>'.$confma['pr_c'].'</priority>' : '';
                            $map_c .= '</url>'."\n";
                        }
                    }
                }
            }
            if ($confma['txt']) {
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
                if ($conf['rewrite']) {
                    include('config/config_rewrite.php');
                    $buffer = preg_replace($in, $out, $buffer);
                }
                file_put_contents('config/sitemap/sitemap.txt', $buffer);
            }
            if ($confma['gen_h']) {
                $map_h = '<url><loc>'.$conf['homeurl'].'/index.php</loc>';
                $map_h .= ($confma['dat_h']) ? '<lastmod>'.$date.'</lastmod>' : '';
                $map_h .= ($confma['fr_h']) ? '<changefreq>'.$confma['fr_h'].'</changefreq>' : '';
                $map_h .= ($confma['pr_h']) ? '<priority>'.$confma['pr_h'].'</priority>' : '';
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
                    $cont .= ($confma['xsl'] && file_exists('config/sitemap/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n" : '';
                    $cont .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$urls.'</urlset>';
                    if ($conf['rewrite']) {
                        include('config/config_rewrite.php');
                        $cont = str_replace($conf['homeurl'].'/', '', $cont);
                        $cont = preg_replace($in, $out, $cont);
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
            $cont .= ($confma['xsl'] && file_exists('config/sitemap/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n".$set : $set;
            if ($conf['rewrite']) {
                include('config/config_rewrite.php');
                $cont = str_replace($conf['homeurl'].'/', '', $cont);
                $cont = preg_replace($in, $out, $cont);
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
function getTranslit($st, $lo='') {
    $st = strtr($st, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => 'yo', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ь' => '', 'ю' => 'yu', 'я' => 'ya', 'Ё' => 'Yo', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => '', 'Ь' => '', 'Ю' => 'Yu', 'Я' => 'Ya'));
    $st = empty($lo) ? $st : mb_strtolower($st);
    $st = preg_replace('#[^a-zA-Z0-9]#', '', $st);
    $st = trim($st);
    return $st;
}

# Social networks code
function getNetworks() {
    global $conf, $confu;
    if ($confu['network_c']) {
        $url = urlencode($conf['homeurl'].'/index.php?name=account&op=network');
        $st = array('[url]' => $url);
        $cont = strtr($confu['network_c'], $st);
    } else {
        $cont = '';
    }
    return $cont;
}

# Get captcha
function getCaptcha($id) {
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
function getImgEncode($img) {
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
function getCompressCss($css) {
    # Remove multiline comment
    $css = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $css);
    # Remove tabs, spaces, newlines
    $css = str_replace(array("\n", "\r", "\t"), ' ', $css);
    # Remove extra spaces
    $css = preg_replace('#\s+#', ' ', $css);
    # Remove spaces that can be removed
    $css = preg_replace('#\s?([\{\}\:\;\,])\s?#', "\\1", $css);
    return $css;
}

# Compress Code
function getCompressCode($code) {
    # Remove multiline comment
    $code = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $code);
    # Remove tabs and extra spaces
    $code = str_replace(array("\t", '  ', '   ', '    '), ' ', $code);
    # Remove other spaces before/after )
    $code = preg_replace(array('#( )+\)#', '#\)( )+#'), ')', $code);
    # Remove spaces that can be removed
    $code = preg_replace('#\s?([\{\=-])\s?#', "\\1", $code);
    return $code;
}

# Compress HTML
function getCompressHtml($html) {
    preg_match_all('#(<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>)#si', $html, $pre);
    $html = preg_replace('#<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>#si', '%pre%', $html);
    $html = preg_replace('#<!–[^\[].+–>#', '', $html);
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

# DELETE
function getCompressCodeOld($cont) {
    # Удаление пробелов между HTML тегов
    $cont = preg_replace('#(?:(?<=\>)|(?<=\/\>))\s+(?=\<\/?)#', '', $cont);
    # Исключение <pre>
    if (false === strpos($cont, '<pre')) $cont = preg_replace('#\s+#', ' ', $cont);
    # Удаление новых строк, за которыми пробелы
    $cont = preg_replace("#[\t\r]\s+#", ' ', $cont);
    # Сохранение комментариев для IE
    $cont = preg_replace('#<!(--)([^\[|\|])^(<!-->.*<!--.*-->)#', '', $cont);
    # Удаленией комментариев CSS
    $cont = preg_replace('#\/\*.*?\*\/#', '', $cont);
        # Удаление табуляторов, замена двойных пробелов одинарным
    $cont = str_replace(array("  ", "\s\s", "\n", "\r", "\t"), ' ', $cont);
    return $cont;
}

# Voting view
function getVoting() {
    global $db, $prefix, $admin_file, $user, $currentlang, $conf, $confv;
    $querylang = ($conf['multilingual'] == 1) ? "(language = '".$currentlang."' OR language = '') AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')" : "date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
    $arg = func_get_args();
    $id = (isset($arg[0])) ? intval($arg[0]) : intval($_GET['id']);
    $votid = (isset($arg[1])) ? ((isset($arg[1])) ? analyze($arg[1]) : "voting") : ((isset($_POST['votid'])) ? analyze($_POST['votid']) : "voting");
    $result = $db->sql_query("SELECT modul, title, questions, answer, enddate, multi, comments, acomm, typ, status FROM ".$prefix."_voting WHERE id = '".$id."' AND ".$querylang);
    if ($db->sql_numrows($result) > 0) {
        $ip = getIp();
        $past = time() - intval($confv['voting_t']);
        $cmod = substr("voting", 0, 2)."-".$id;
        $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : "";
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->sql_query("DELETE FROM ".$prefix."_rating WHERE time < '".$past."' AND modul = 'voting'");
        list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_rating WHERE (mid = '".$id."' AND modul = 'voting' AND host = '".$ip."') OR (mid = '".$id."' AND modul = 'voting' AND uid = '".$uid."' AND uid != '0')"));
        list($modul, $title, $questions, $answer, $enddate, $multi, $comments, $acomm, $typ, $status) = $db->sql_fetchrow($result);
        $rate = ($cookies == $id || $num > 0 || strtotime($enddate) <= time()) ? 1 : 0;
        if ($typ || !$typ && !$rate) {
            $questions = explode("|", $questions);
            $answer = explode("|", $answer);
            $vote = array_sum($answer);
            $form = (!$rate) ? "<form name=\"voting\" id=\"form".$votid."\" method=\"post\">" : "";
            $cont = tpl_eval("voting-open", $form, $title);
            $pn = 0;
            for ($i = 0; $i < count($questions); $i++) {
                $pn++;
                if ($pn > 5) $pn = 1;
                $n = $i + 1;
                if ($vote > 0) {
                    $proc = 100 * $answer[$i] / $vote;
                    $im_w = (int)$proc - 10;
                    $procent = number_format($proc, 2);
                } else {
                    $procent = "0.00";
                    $im_w = 1;
                }
                if (!$rate) {
                    $itype = ($multi) ? "checkbox" : "radio";
                    $cont .= tpl_func("voting-post", $id, $n, $itype, "questions[]", $questions[$i]);
                } else {
                    $cont .= tpl_func("voting-view", $questions[$i], text_filter($questions[$i]), $n, $pn, $procent, _VOTES, $answer[$i]);
                }
            }
            list($vnum) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_voting WHERE ".$querylang));
            $admin = (is_moder("voting") && $votid == "voting") ? add_menu("<a href=\"".$admin_file.".php?op=voting_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=voting_delete&amp;id=".$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$title."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>") : "";
            $post = (!$rate) ? "<span OnClick=\"AjaxLoad('POST', '1', '".$votid."', 'go=1&amp;op=avoting_save&amp;id=".$id."&amp;votid=".$votid."', { 'questions%5B%5D':'"._SEROR1."' }); return false;\" title=\""._VOTE."\" class=\"sl_but_blue\">"._VOTE."</span>" : "";
            $polls = ($vnum > 1) ? "<a href=\"index.php?name=voting\" title=\""._POLLS."\" class=\"sl_but\">"._POLLS."</a>" : "";
            $votes = (!$modul && $votid != "voting") ? "<a href=\"index.php?name=voting&amp;op=view&amp;id=".$id."\" title=\""._VOTES."\" class=\"sl_votes\">"._VOTES.": ".$vote."</a>" : "<span class=\"sl_votes\">"._VOTES.": ".$vote."</span>";
            $comm = (!$modul && $acomm) ? "<a href=\"index.php?name=voting&amp;op=view&amp;id=".$id."#".$id."\" title=\""._COMMENTS."\" class=\"sl_coms\">"._COMMENTS.": ".$comments."</a>" : "";
            $formend = (!$rate) ? "</form>" : "";
            $cont .= tpl_eval("voting-close", $admin, $post, $polls, $votes, $comm, $formend);
        } else {
            $cont = setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _VCLINFO));
        }
    } else {
        $cont = setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
    }
    return $cont;
}

# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad($tcache = 2) {
    static $cache = ['time' => 0, 'cpu' => _NO_INFO, 'info' => _NO_INFO];
    if (time() - $cache['time'] < $tcache) return [$cache['cpu'], $cache['info']];
    $percent = null;
    if (stristr(PHP_OS, 'WIN')) {
        $out = [];
        $cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
        @exec($cmd, $out);
        if (!empty($out)) {
            $val = str_replace(',', '.', trim($out[0]));
            if (is_numeric($val)) $percent = (float)$val;
        }
        if ($percent === null) {
            $out = [];
            $cmd = 'wmic cpu get loadpercentage /all';
            @exec($cmd, $out);
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
function getVariables() {
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
function getUserNews($num) {
    global $confu, $user;
    $num = (!empty($user[3]) && $user[3] <= $num && $confu['news'] == 1) ? intval($user[3]) : intval($num);
    return $num;
}

# Random password generation
function getPass($m) {
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
function getProtocol() {
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
function user_news($unum, $mnum) {
    global $confu;
    $num = (!empty($unum) && $unum <= $mnum && $confu['news'] == 1) ? intval($unum) : intval($mnum);
    return $num;
}

# Сохранение конфигураций в файл DELETE
function save_conf($fp, $arr, $type='', $var='') {
    if (file_exists($fp) && $arr) {
        if (is_array($arr) && $var) {
            $cont = "\$".$var." = array();\n";
            foreach ($arr as $key => $value) $cont .= (preg_match('#<<<HTML#', $value)) ? "\$".$var."['".$key."'] = ".$value.";" : "\$".$var."['".$key."'] = \"".$value."\";";
        } else {
            $cont = $arr;
        }
        $cons = empty($type) ? 'FUNC_FILE' : 'ADMIN_FILE';
        $cont = "<?php\nif (!defined('".$cons."')) die('Illegal file access');\n\n".$cont;
        file_put_contents($fp, $cont, LOCK_EX);
    }
}

# Clear text from HTML and BB code
function getTextClean($text, $id) {
    $text = htmlspecialchars(strip_tags(htmlspecialchars_decode($text)), ENT_QUOTES);
    $text = ($id == '1') ? preg_replace(array('#\[attach=(.*?)\s(.*?)\]#si', '#\[img=(.*?)\](.*)\[/img\]#si', '#\[.+\]#iUs', '#\s+#s'), array('', '', '', ' '), $text) : preg_replace(array('#\[.+\]#iUs', '#[^\pL0-9]#siu', '#\s+#s'), array('', ' ', ','), $text);
    $text = trim($text);
    return $text;
}

# Get the image from the text
function getImgText($text, $type='') {
    global $conf;
    if (preg_match('#\[attach=(.*?)\s(.*?)\]#i', $text, $match)) {
        $img = (!$type) ? 'uploads/'.$conf['name'].'/thumb/'.trim($match[1]) : 'uploads/'.$conf['name'].'/'.trim($match[1]);
    } else {
        preg_match('#\[img=(.*?)\](.*)\[/img\]#i', $text, $match);
        $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
    }
    $img = empty($img) ? false : (file_exists($img) ? $img : false);
    return $img;
}

# Format SEO url
$confse = [
    'rewrite' => false,   // true = SEO-Link, false = klassischer Link
    'sep'     => '/',    // Separator für Basis-Segmente (name, op, id)
    'title'   => true,   // title einfügen oder nicht
    'ctitle'  => true,   // ctitle einfügen oder nicht
    'tsep'    => '',    // Separator nur für Title / CTitle
];

function getSeoUrl(array $params): string {
    global $confse;
    $sep  = $confse['sep'] ?? '-';
    $tsep = $confse['tsep'] ?? '-';

    // Basis-Segmente: name, op, id
    $segments = [
        $params['name'] ?? 'home',
        $params['op'] ?? 'view',
        $params['id'] ?? '0',
    ];

    // Optional: title / ctitle mit eigenem Separator
    foreach (['title', 'ctitle'] as $key) {
        if (!empty($confse[$key]) && !empty($params[$key])) {
            $segments[] = slugify($params[$key], $tsep);
        }
    }

    // Rückgabe: SEO-Link oder klassischer Link
    return ($confse['rewrite'] ?? false)
        ? implode($sep, $segments)
        : 'index.php?name=' . $segments[0] . '&op=' . $segments[1] . '&id=' . $segments[2];
}

function slugify(string $text, string $sep = '-'): string {
    $text = trim($text);

    // Russische Buchstaben transliterieren
    $rus = [
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'Zh',
        'З'=>'Z','И'=>'I','Й'=>'I','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O',
        'П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'Kh','Ц'=>'Ts',
        'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ы'=>'Y','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
        'Ь'=>'','Ъ'=>'',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts',
        'ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $text = strtr($text, $rus);

    // Sonderzeichen ersetzen
    $text = preg_replace('~[^a-zA-Z0-9]+~', $sep, $text);
    $text = trim($text, $sep);

    return strtolower($text);
}


function getHref($meta) {
    global $prefix, $db, $conf, $confse;
    
    if (is_array($meta)) {
        $href = $meta['0'];
        if ($conf['rewrite']) {
            $query = explode('&', str_replace('&amp;', '&', $href));
            foreach($query as $q) {
                list($key, $value) = explode('=', $q);
                $ar[] = $value;
            }
            $url = implode(urldecode($conf['sep']), $ar);
        } else {
            $url = (stristr($href, '#') === false) ? $href : stristr($href, '#', true);
        }
        $time = empty($meta['1']) ? '0000-00-00 00:00:00' : $meta['1'];
        $mtime = empty($meta['2']) ? '0000-00-00 00:00:00' : $meta['2'];
        $title = empty($meta['3']) ? '' : getTextClean($meta['3'], 1);
        if (empty($meta['4'])) {
            $desc = '';
            $keys = '';
            $img = '';
        } else {
            $desc = ($conf['adesc']) ? cutstr(getTextClean($meta['4'], 1), $confse['dletter'], 2) : '';
            $keys = '0';
            if ($conf['akeys']) {
                $keyg = $title.' '.getTextClean($meta['4'], 2);
                $keyg = explode(',', mb_strtolower($keyg, 'utf-8'));
                $keyg = array_diff($keyg, explode(',', $conf['dkeys']));
                $res = array_count_values($keyg);
                arsort($res, SORT_NUMERIC);
                if (is_array($res)) {
                    $i = 1;
                    foreach($res as $k => $v) {
                        if (mb_strlen($k, 'utf-8') >= $conf['kletter']) {
                            $akeys[] = $k;
                            if ($i == $conf['kwords']) break;
                            $i++;
                        }
                    }
                    $keys = implode(',', $akeys);
                }
            }
            $img = getImgText($meta['4']);
            $img = empty($img) ? '' : $img;
        }
        $ctitle = empty($meta['5']) ? '' : $meta['5'];
        $cdesc = empty($meta['6']) ? '' : $meta['6'];
        $cimg = empty($meta['7']) ? '' : $meta['7'];
        
        ###
        $url = urlencode($url);
        $href = urlencode($href);
        #list($durl, $dmtime) = $db->sql_fetchrow($db->sql_query("SELECT sl_url, sl_mtime FROM ".$prefix."_seo WHERE sl_url = '".$url."'"));
        #$result = $db->sql_query("SELECT url FROM ".$prefix."_seo WHERE url = '".urlencode($url)."'");

        if ($url == $durl) {
            if ($mtime > $dmtime) {
                # echo "UPDATE";
                #$db->sql_query("UPDATE ".$prefix."_seo SET sl_url = '".$url."', sl_link = '".$href."', sl_time = '".$time."', sl_mtime = '".$mtime."', sl_title = '".$title."', sl_desc = '".$desc."', sl_keys = '".$keys."', sl_img = '".$img."', sl_ctitle = '".$ctitle."', sl_cdesc = '".$cdesc."', sl_cimg = '".$cimg."' WHERE sl_url = '".$url."'");
            }
            #$result = $db->sql_query("SELECT mtime FROM ".$prefix."_seo WHERE mtime < '".$mtime."'");
            #if ($db->sql_numrows($result) > 0) {
                #$db->sql_query("UPDATE ".$prefix."_seo SET uname = '".$uname."', time = '".$ctime."', host_addr = '".$ip."', guest = '".$guest."', module = '".$name."', url = '".$url."' WHERE uname = '".$uname."'");
            #}
        } else {
            #$db->sql_query("INSERT INTO ".$prefix."_seo (url, link, time, mtime, title, desc, keys, img, ctitle, cdesc, cimg) VALUES ('".str_replace('&', '&amp;', $url)."', '".str_replace('&', '&amp;', $href)."', NOW(), '".$mtime."', '".$title."', '".$desc."', '".$keys."', '".$img."', '".$ctitle."', '".$cdesc."', '".$cimg."')");
            #$db->sql_query("INSERT INTO ".$prefix."_seo VALUES (NULL, '".$url."', '".$href."', '".$time."', '".$mtime."', '".$title."', '".$desc."', '".$keys."', '".$img."', '".$ctitle."', '".$cdesc."', '".$cimg."')");
        }
        ###
        
        /*
        $marray = $href.'||'.$time.'||'.$mtime.'||'.$title.'||'.$desc.'||'.$keys.'||'.$img.'||'.$ctitle.'||'.$cdesc.'||'.$cimg;
        $array = array($url => $marray);
        $result = !empty($confru[$url]) ? array_udiff_assoc(explode('||', $marray), explode('||', $confru[$url]), 'isCompare') : false;
        if (empty($confru[$url])) {
            $cont = array_merge($confru, $array);
            $save = 1;
        } elseif ($result) {
            $cont = array_replace($confru, array($url => $confru[$url]), $array);
            $save = 1;
        } else {
            $save = 0;
        }
        if ($save) {
            ksort($cont, SORT_STRING);
            save_conf('config/config_rules.php', $cont, '', 'confru');
        }
        */
        $url = ($conf['rewrite']) ? urldecode($url) : 'index.php?'.str_replace('&', '&amp;', urldecode($href));
    } else {
        $url = false;
    }
    return $url;
}

# Format theme file
function getThemeFile($name) {
    global $home, $conf, $op;
    static $theme;
    $theme = (!isset($theme)) ? get_theme() : $theme;
    $cat = (isset($_GET['cat'])) ? intval($_GET['cat']) : '';
    if ($home) {
        $fname = (file_exists('templates/'.$theme.'/'.$name.'-home.html')) ? $name.'-home' : $name;
    } elseif (isset($conf['template'])) {
        $fname = (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['template'].'.html')) ? $name.'-'.$conf['template'] : $name;
    } elseif (isset($conf['name']) && $op) {
        if (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['name'].'-'.$op.'.html')) {
            $fname = $name.'-'.$conf['name'].'-'.$op;
        } elseif (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['name'].'.html')) {
            $fname = $name.'-'.$conf['name'];
        } else {
            $fname = $name;
        }
    } elseif (isset($conf['name']) && $cat) {
        if (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['name'].'-cat-'.$cat.'.html')) {
            $fname = $name.'-'.$conf['name'].'-cat-'.$cat;
        } elseif (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['name'].'.html')) {
            $fname = $name.'-'.$conf['name'];
        } else {
            $fname = $name;
        }
    } elseif (isset($conf['name'])) {
        $fname = (file_exists('templates/'.$theme.'/'.$name.'-'.$conf['name'].'.html')) ? $name.'-'.$conf['name'] : $name;
    } else {
        $fname = $name;
    }
    $index = (file_exists('templates/'.$theme.'/'.$fname.'.html')) ? 'templates/'.$theme.'/'.$fname.'.html' : 0;
    return $index;
}

# Determining the load time
function getTimeLoads() {
    global $db, $sgtime;
    $ttime = sprintf('%.3f', microtime(true) - $sgtime);
    $qnums = $db->qnum;
    $sqltime = sprintf('%.3f', $db->sqltime);
    $cont = _GENERATION.': '.$ttime.' '._SEC.'. '._AND.' '.$qnums.' '._GENERATION_DB.' '.$sqltime.' '._SEC.'.';
    return $cont;
}

### End of new features

# Format theme
function get_theme() {
    global $user, $conf;
    if (!defined('ADMIN_FILE') && is_user()) {
        $utheme = $user[5];
        $theme = ($utheme != '' && is_dir('templates/'.$utheme)) ? $utheme : $conf['theme'];
    } elseif (!defined('ADMIN_FILE')) {
        $theme = $conf['theme'];
    } elseif (defined('ADMIN_FILE')) {
        $theme = 'admin';
    }
    return $theme;
}

# Format Time
function datetime($id, $name, $time, $max, $class) {
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
function save_datetime($id, $name="") {
    if ($name) {
        $date = (isset($_POST[$name])) ? $_POST[$name] : ((isset($_GET[$name])) ? $_GET[$name] : "");
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
function format_time($time, $string='') {
    $string = ($string) ? $string : _DATESTRING;
    $cont = date($string, strtotime($time));
    return $cont;
}

# Size filter
function files_size($size) {
    $name = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $cont = ($size) ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).' '.$name[$i] : intval($size).' Bytes';
    return $cont;
}

# Format new graphic
function new_graphic($time) {
    $data = time() - strtotime($time);
    $img = "";
    if ($data < 86400) $img = "<span title=\""._NEWTODAY."\" class=\"sl_n_day\"></span>";
    if (($data > 86400) && ($data < 259200)) $img = "<span title=\""._NEWLAST3DAYS."\" class=\"sl_n_days\"></span>";
    if (($data > 259200) && ($data < 604800)) $img = "<span title=\""._NEWTHISWEEK."\" class=\"sl_n_week\"></span>";
    return $img;
}

# Format radio form
function radio_form($var, $name, $id="") {
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
function get_gender($name, $typ, $class) {
    $gender = array(_NO_INFO, _MAN, _WOMAN);
    $cont = "<select name=\"".$name."\" class=\"sl_field ".$class."\">";
    foreach ($gender as $key => $val) {
        $select = ($key == $typ) ? " selected" : "";
        $cont .= "<option value=\"".$key."\"".$select.">".$val."</option>";
    }
    $cont .= "</select>";
    return $cont;
}

# Format gender
function gender($gender) {
    if ($gender == 2) {
        $gen = _WOMAN;
    } elseif ($gender == 1) {
        $gen = _MAN;
    } else {
        $gen = _NO_INFO;
    }
    return $gen;
}

# Format search highlight
function search_color($sourse, $word) {
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
        $taglist = array();
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
function replace_break($text) {
    global $admin, $conf;
    if ($text) {
        $editor = intval(substr($admin[3], 0, 1));
        $out = ((defined("ADMIN_FILE") && $editor == 1) || (!defined("ADMIN_FILE") && $conf['redaktor'] == 1)) ? preg_replace("#<br.*>#i", "", $text) : $text;
        return $out;
    }
}

# User country information
function user_geo_ip($ip, $id) {
    global $conf;
    if ((PHP_VERSION >= "5") && $conf['geo_ip'] && preg_match("#([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3})#", $ip)) {
        include_once("core/geo_ip.php");
        $geoip = geo_ip::getInstance("core/geo_ip.dat");
        if ($id == 1) {
            $cont = $geoip->lookupCountryCode($ip);
        } elseif ($id == 2) {
            $cont = $geoip->lookupCountryName($ip);
        } elseif ($id == 3) {
            $name = $geoip->lookupCountryName($ip);
            $img = str_replace(" ", "_", strtolower($name));
            $imgl = (file_exists(img_find("language/".$img.".png"))) ? img_find("language/".$img.".png") : (($img == "?") ? img_find("language/white.png") : img_find("language/white.png"));
            $cont = "<img src=\"".$imgl."\" alt=\"".$name."\" title=\"".$name."\" class=\"sl_flag\">";
        } elseif ($id == 4) {
            $name = $geoip->lookupCountryName($ip);
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
function user_sinfo($id="") {
    global $prefix, $db, $conf;
    if ($conf['session']) {
        $who_online = ""; $m = 0; $b = 0; $u = 0; $i = 0;
        $result = $db->sql_query("SELECT uname, time, host_addr, guest, module FROM ".$prefix."_session ORDER BY uname");
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
function user_sainfo($id="") {
    global $prefix, $db, $conf;
    if ($conf['session'] && is_admin()) {
        $a = $b = $m = $u = $i = 0;
        $who_online = array("0" => "", "1" => "", "2" => "", "3" => "");
        $content_who = "";
        $result = $db->sql_query("SELECT uname, time, host_addr, guest, module, url FROM ".$prefix."_session ORDER BY uname");
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
function adminblock() {
    global $prefix, $db, $conf, $admin_file;
    if (is_admin()) {
        $cont = '<table class="sl_table_block"><tr><td><a href="'.$admin_file.'.php" title="'._ADMINMENU.'">'._ADMINMENU.'</a></td></tr>'
        .'<tr><td><a href="'.$admin_file.'.php?op=logout" title="'._LOGOUT.'">'._LOGOUT.'</a></td></tr></table>';
        if (is_admin_god()) {
            list($title, $content) = $db->sql_fetchrow($db->sql_query("SELECT title, content FROM ".$prefix."_blocks WHERE bkey = 'admin'"));
            $cont .= '<hr>'.$content;
        }
        $a_title = ($title) ? $title : _ADMINS;
        return setTemplateBlock('block-left', array('{%title%}' => $a_title, '{%content%}' => $cont, '{%id%}' => '7')).setTemplateBlock('block-left', array('{%title%}' => _WHO, '{%content%}' => '<div id="repsainfo">'.user_sainfo(1).'</div>', '{%id%}' => '8'));
    }
}

# Newsletter send
function updateNewsletter(): void {
    global $prefix, $db, $conf;
    if ($conf['newsletter']) {
        $result = $db->sql_query("SELECT id, title, content, mails FROM ".$prefix."_newsletter WHERE mails != ''");
        if ($db->sql_numrows($result) > 0) {
            list($id, $title, $content, $mails) = $db->sql_fetchrow($result);
            $ncount = intval($conf['newslettercount']);
            $id = intval($id);
            $mails = explode(",", $mails);
            $outmail = array_slice($mails, 0, $ncount);
            $inmail = implode(",", array_slice($mails, $ncount));
            $db->sql_query("UPDATE ".$prefix."_newsletter SET mails = '".$inmail."', send = send+".$ncount.", endtime = NOW() WHERE id = '".$id."'");
            foreach ($outmail as $val) if ($val != "") mail_send($val, $conf['adminmail'], $title, bb_decode($content, "all"), 0, 3);
            if (!$inmail) {
                $cont = array('newsletter' => '0');
                doConfig('config/config_global.php', 'conf', $cont, $conf, '');
            }
        }
    }
}

# User info link
function user_info($name) {
    global $confu;
    if ($name) {
        $link = ($confu['prof'] != 1 || ($confu['prof'] == 1 && is_user()) || is_admin()) ? "<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($name)."\" title=\""._PERSONALINFO."\">".$name."</a>" : $name;
    } else {
        $link = "";
    }
    return $link;
}

# Show kasse
function show_kasse($info="") {
    global $db, $prefix, $confso;
    $shop = (isset($_COOKIE['shop'])) ? base64_decode($_COOKIE['shop']) : "";
    $info = (empty($info)) ? $shop : base64_decode($info);
    $cookies = (preg_match("#[^0-9,]#", $info)) ? "" : $info;
    if ($cookies) {
        $massiv = explode(",", $cookies);
        $mid= implode(",", array_unique($massiv));
        $result = $db->sql_query("SELECT id, time, title, preis FROM ".$prefix."_products WHERE id IN (".$mid.")");
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
            $cont .= tpl_func("kasse-basic", $id, $ptitle, $i, $preis." ".$confso['valute'], $plus, $minus);
        }
        $cart = "<a href=\"index.php?name=shop&amp;op=kasse\" title=\""._SCACH."\" class=\"sl_shop_kasse\">"._SCACH."</a>";
        $total = "<span title=\""._PARTNERGES."\" class=\"sl_shop_total\">"._PARTNERGES.": ".$preistotal." ".$confso['valute']."</span>";
        return tpl_eval("kasse-open", _PBASKET, _ID, _PRODUCT, cutstr(_QUANTITY, 3, 1), _PREIS, _FUNCTIONS).$cont.tpl_eval("kasse-close", $cart, $total);
    }
}

# Add kasse
function add_kasse() {
    global $db, $prefix, $confso;
    $id = (isset($_GET['id'])) ? intval($_GET['id']) : "";
    $cookies = (preg_match("#[^0-9,]#", base64_decode($_COOKIE['shop']))) ? "" : base64_decode($_COOKIE['shop']);
    if ($id) {
        setcookie("shop", false);
        if ($cookies) {
            $info = base64_encode($cookies.",".$id);
            setcookie("shop", $info, time() + $confso['shop_t']);
        } else {
            $info = base64_encode($id);
            setcookie("shop", $info, time() + $confso['shop_t']);
        }
    }
    echo show_kasse($info);
}

# Delete kasse
function del_kasse() {
    global $confso;
    $id = (isset($_GET['id'])) ? intval($_GET['id']) : "";
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
        setcookie("shop", $info, time() + $confso['shop_t']);
    }
    echo show_kasse($info);
}

# Format user warnings
function warnings($warnings) {
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
function ajax_rating($typ, $id, $mod, $rat, $scor, $obj="", $stl="") {
    global $confra;
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
        $con = explode("|", $confra[strtolower($mod)]);
        if (($con[1] && $id && $mod) || ($rat && $scor)) {
            $content = (($con[1] && $typ) || ($con[1] && !$con[2] && !$typ)) ? "<div id=\"rep".$id.$obj."\" class=\"".$crate."\">".$imgr."</div>" : "<div class=\"".$crate."\">".$img."</div>";
        } else {
            $content = "";
        }
    }
    return $content;
}

# Show editor files
function show_files() {
    global $conf, $user;
    include("config/config_uploads.php");
    $id = (isset($_GET['id'])) ? analyze($_GET['id']) : 0;
    $dir = (isset($_GET['dir'])) ? strtolower($_GET['dir']) : "";
    $gzip = (isset($_GET['cid'])) ? intval($_GET['cid']) : 0;
    $con = explode("|", $confup[$dir]);
    $connum = (intval($con[7])) ? $con[7] : "50";
    $eallf = (is_moder()) ? intval($con[8]) : intval($con[9]);
    $file = (isset($_GET['file'])) ? text_filter($_GET['file']) : "";
    $num = ($gzip) ? $gzip : "1";
    $uname = (is_user()) ? intval($user[0]) : 0;
    $path = "uploads/".$dir."/";
    if (is_moder($dir) && $file && $dir) {
        if (!$gzip) {
            unlink($path.$file);
        } else {
            zip_compress($path.$file, $path.$file);
        }
    }
    $dh = opendir($path);
    while ($entry = readdir($dh)) {
        if ($entry != "." && $entry != ".." && $entry != "index.html" && !is_dir($path.$entry)) $files[] = array(filemtime($path.$entry), $entry);
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
                $ftype = array("png", "jpg", "jpeg", "gif", "bmp");
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
function stream($url, $name) {
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
function anti_spam($mail) {
    preg_match("#^(.*?)(@)(.*?)$#", $mail, $info);
    $cont = "<script>\"mysi\".AddMail('".$info[1]."', '".$info[3]."');</script><noscript>".$info[1]."<!-- slaed --><span>&#64;</span><!-- slaed -->".$info[3]."</noscript>";
    return $cont;
}

# Format letter
function letter($mod) {
    global $prefix, $db, $user;
    if ($mod == "faq") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_faq WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "files") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_files WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "help") {
        $uid = intval($user[0]);
        $result = $db->sql_query("SELECT title FROM ".$prefix."_help WHERE time <= NOW() AND pid = '0' AND uid = '".$uid."'");
    } elseif ($mod == "links") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_links WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "media") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_media WHERE date <= NOW() AND status != '0'");
    } elseif ($mod == "news") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_news WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "pages") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_pages WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == "shop") {
        $result = $db->sql_query("SELECT title FROM ".$prefix."_products WHERE time <= NOW() AND active != '0'");
    } else {
        $result = "";
    }
    if ($result) {
        while(list($title) = $db->sql_fetchrow($result)) $letdb[] = ucfirst(mb_substr(trim($title), 0, 1, "utf-8"));
        $alpha = array_unique($letdb);
    } else {
        $alpha = array();
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
function add_menu($links) {
    if ($links) {
        $links = explode("||", $links);
        $cont = "<nav class=\"sl_menu\"><ul><li><span class=\"sl_but_red\">"._EDITOR."</span><ul>";
        foreach ($links as $val) if ($val != "") $cont .= "<li>".$val."</li>";
        $cont .= "</ul></li></ul></nav>";
        return $cont;
    }
}

# Format title tips
function title_tip($data) {
    $data = is_array($data) ? implode("<br>", $data) : $data;
    $tip = "<nav class=\"sl_tip\"><div>".$data."</div></nav>";
    return $tip;
}

# Admin status
function ad_status($link, $id, $typ="", $text="") {
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
function mailto($mail) {
    global $conf;
    return "<a href=\"mailto:".$mail."?subject=".$conf['sitename']."\" target=\"_blank\">".$mail."</a>";
}

# Add save button
function ad_save() {
    global $conf;
    $arg = func_get_args();
    $cont = "<select name=\"posttype\" class=\"sl_field\">";
    if (empty($arg[3])) $cont .= "<option value=\"preview\">"._PREVIEW."</option>";
    $cont .= "<option value=\"save\">"._SEND."</option>";
    $cont .= ($arg[1]) ? "<option value=\"delete\">"._DELETE."</option></select>" : "</select>";
    $cont .= ($arg[0] && $arg[1]) ? "<input type=\"hidden\" name=\"".$arg[0]."\" value=\"".$arg[1]."\">" : "";
    $cont .= "<input type=\"hidden\" name=\"op\" value=\"".$arg[2]."\">"
    ." <input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\">";
    return $cont;
}

# Find img
function img_find($img) {
    global $conf, $theme;
    $theme = (!isset($theme)) ? get_theme() : $theme;
    return 'templates/'.$theme.'/images/'.$img;
}

# Format select RSS
function rss_select() {
    global $conf;
    include("config/config_rss.php");
    $fieldc = explode("||", $confrs['rss']);
    $url = (isset($_POST['url'])) ? url_filter($_POST['url']) : "";
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
function rss_read($url, $id) {
    if ($url) {
        include("config/config_rss.php");
        $url = str_replace(array("&#038;", "&amp;"), "&", $url);
        $url = (!preg_match("#http\:\/\/#i", $url)) ? "http://".$url : $url;
        $content = file_get_contents($url);
        preg_match("#encoding=\"(.*)\"#i", $content, $val);
        if (strtolower($val[1]) != "utf-8") $content = iconv($val[1], "utf-8", $content);
        if ($content) {
            $title = parse_url($url);
            $title = $title['host'];
            preg_match_all("#<item>(.*)</item>#Uism", $content, $items, PREG_PATTERN_ORDER);
            if ($items[1]) {
                $number = ($confrs['max'] > count($items[1])) ? count($items[1]) : $confrs['max'];
                $cont = "";
                for ($i = 0; $i < $number; $i++) {
                    preg_match("#<title>(.*)</title>#Uism", $items[1][$i], $rss_title);
                    preg_match("#<pubDate>(.*)</pubDate>#Uism", $items[1][$i], $rss_date);
                    preg_match("#<guid>(.*)</guid>(.*)#Uism", $items[1][$i], $rss_guid);
                    preg_match("#<description>(.*)</description>#Uism", $items[1][$i], $rss_desc);
                    $temp = $confrs['temp'];
                    $temp = str_replace("[title]", $rss_title[1], $temp);
                    $temp = str_replace("[date]", date(_DATESTRING, strtotime($rss_date[1])), $temp);
                    $temp = str_replace("[guid]", $rss_guid[1], $temp);
                    $temp = str_replace("[description]", text_filter(html_entity_decode(str_replace("]]>", "", $rss_desc[1]))), $temp);
                    $cont .= $temp;
                }
                $cont = ($id) ? $cont : "<h2>"._RSS_FROM.": <a href=\"".htmlspecialchars($url)."\" target=\"_blank\" title=\""._RSS_FROM.": ".$title."\">".$title."</a></h2>".$cont;
            } else {
                $cont = ($id) ? "" : tpl_warn("warn", _RSS_PROBLEM, "", "", "warn");
            }
        } else {
            $cont = ($id) ? "" : tpl_warn("warn", _RSS_PROBLEM, "", "", "warn");
        }
        return $cont;
    }
}

# Load RSS
function rss_load($bid) {
    global $prefix, $db;
    $bid = intval($bid);
    list($title, $content, $url, $refresh, $otime) = $db->sql_fetchrow($db->sql_query("SELECT title, content, url, refresh, time FROM ".$prefix."_blocks WHERE bid = '".$bid."'"));
    $past = time() - $refresh;
    if ($otime < $past) {
        $btime = time();
        $content = rss_read($url, 1);
        $db->sql_query("UPDATE ".$prefix."_blocks SET content = '".$content."', time = '".$btime."' WHERE bid = '".$bid."'");
    }
    echo setTemplateBlock('', array('{%title%}' => $title, '{%content%}' => $content));
}

# Preview
function preview() {
    $arg = func_get_args();
    $fields = ($arg[0]) ? "<b>".$arg[0]."</b>" : "";
    $fields1 = ($arg[1]) ? (($fields) ? "<br><br>".bb_decode($arg[1], $arg[4]) : bb_decode($arg[1], $arg[4])) : "";
    $fields2 = ($arg[2]) ? "<br><br>".bb_decode($arg[2], $arg[4]) : "";
    $fields3 = ($arg[3]) ? "<br><br>".fields_out(bb_decode($arg[3], $arg[4]), $arg[4]) : "";
    return tpl_eval("preview", _PREVIEW, $fields, $fields1, $fields2, $fields3);
}

# Defined constant
function defconst($con) {
    $out = (defined($con)) ? constant($con) : $con;
    return $out;
}

# Defined lang modul names constant
function deflmconst($con) {
    $val = array('account' => _ACCOUNT, 'album' => _ALBUM, 'all' => _ALL, 'auto_links' => _A_LINKS, 'clients' => _CLIENTS, 'contact' => _FEEDBACK, 'content' => _CONTENT, 'faq' => _FAQ, 'files' => _FILES, 'forum' => _FORUM, 'gallery' => _ALBUM, 'help' => _HELP, 'info' => _INFO, 'radio' => _RADIO, 'jokes' => _JOKES, 'links' => _LINKS, 'main' => _MAIN, 'media' => _MEDIA, 'members' => _USERS, 'money' => _MONEY, 'news' => _NEWS, 'order' => _ORDER, 'pages' => _PAGES, 'recommend' => _RECOMMEND, 'rss_info' => _RSS, 'search' => _SEARCH, 'shop' => _SHOP, 'users' => _TOPUSERS, 'voting' => _VOTING, 'whois' => _WHOIS, 'sitemap' => _SITEMAP);
    return strtr($con, $val);
}

# Defined lang constant
function deflang($con) {
    $val = array('english' => _ENGLISH, 'french' => _FRENCH, 'german' => _GERMAN, 'polish' => _POLISH, 'russian' => _RUSSIAN, 'ukrainian' => _UKRAINIAN);
    return strtr($con, $val);
}

# Fields in
function fields_in($fieldb, $mod) {
    global $conf;
    include('config/config_fields.php');
    $mod = strtolower($mod);
    $style = (defined('ADMIN_FILE')) ? 'sl_field sl_form' : 'sl_field '.$conf['style'];
    $fieldc = $conffi[$mod];
    if (!isset($_POST['field'])) {
        $fieldb = $fieldb;
    } else {
        $fieldb = fields_save($_POST['field']);
    }
    $fieldb = explode('|', $fieldb);
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
function fields_out($fieldb, $mod) {
    include("config/config_fields.php");
    $mod = strtolower($mod);
    if ($fieldb && $mod) {
        $fieldc = $conffi[$mod];
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
function domain($url, $str="") {
    $massiv = explode(",", $url);
    $str = intval($str);
    foreach ($massiv as $val) $dom[] = "<a href=\"".$val."\" target=\"_blank\" title=\""._DOWNLLINK."\">".(($str) ? cutstr(preg_replace("/http\:\/\/|www./", "", $val), $str) : preg_replace("/http\:\/\/|www./", "", $val))."</a>";
    return implode(", ", $dom);
}

# Check bot
function is_bot() {
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
function from_bot() {
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
function engines_word($refer) {
    $engines = array("images.google." => array("q", "prev"), "bing.com" => "q", ".alot." => "q", "a993.com" => "q1", "abcsok." => "q", "alltheweb." => "q", "altavista." => "q", "aol." => array("q", "query", "encquery"), "aolsvc." => "query", "avantfind.com" => "keywords", "bonvote.com" => "search", "bonweb.com" => "search", "comcast.net" => "q", "conduit." => "q", "eniro.se" => "search_word", "excite." => "search", "google." => array("q", "as_q"), "gogo.ru" => "q", "yandex." => array("text", "query"), "ya.ru" => "text", "hotbot." => "query", "icerocket.com" => "q", "icq.com" => "q", "isheyka.com" => "q", "midco.net" => "q", "live.com" => "q", "msn." => "q", "yahoo." => array("p", "k"), "search." => "q", "kvasir.no" => "q", "myway.com" => "searchfor", "netscape." => array("q", "query"), "oceanfree.net" => "as_q", "qip.ru" => "query", "sweetim.com" => "q", "tut.by" => "query", "ukr.net" => "search_query", "search.oboz.ua" => "k", "search.www.infoseek.co.jp" => "qt", ".setooz.com" => "query", "toile.com" => "q", "vinden.nl" => "q", ".i.ua" => "q", ".mail.ru" => array("q", "tag"), ".onru.ru" => "q", "aport.ru" => "r", "find.ru" => "text", "gde.ru" => array("keywords", "query", "t", "search_query", "id"), "go.km.ru" => "sq", "meta.ua" => "q", "metabot.ru" => "st", "nerus.ru" => "query", "nigma.ru" => array("s", "pq"), "nova.rambler.ru" => "query", "poisk.ru" => "text", "protonet.ru" => "q", "rambler.ru" => "query", "tyndex.ru" => "pnam", "webalta.ru" => "q", "exactseek.com" => array("q", "query"), "lycos." => "query", "ask." => "q", "cnn." => "query", "looksmart." => "qt", "about." => "terms", "mamma." => "query", "gigablast." => "q", "voila." => "rdata", "virgilio." => "qs", "baidu." => "wd", "alice." => "qs", "najdi." => "q", "club-internet." => "q", "mama." => "query", "seznam." => "q", "netsprint." => "q", "szukacz." => "q", "yam." => "k", "pchome." => "q");
    
    $refer= str_replace(array("&#038;", "&amp;"), "&", $refer);
    $tmp = parse_url(urldecode(trim($refer)));
    $site = $tmp['host'];
    $str = $tmp['query'];
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
function is_user($usr="") {
    global $prefix, $db, $user, $confu;
    static $usertrue;
    if (!isset($usertrue) && $user) {
        $uid = intval(substr($user[0], 0, 11));
        $una = htmlspecialchars(substr($user[1], 0, 25));
        $pwd = htmlspecialchars(substr($user[2], 0, 40));
        $ip = getIp();
        if ($uid != "" && $pwd != "") {
            if ($confu['check'] == "0") {
                list($pass) = $db->sql_fetchrow($db->sql_query("SELECT user_password FROM ".$prefix."_users WHERE user_id = '".$uid."' AND user_name = '".$una."'"));
                if ($pass == $pwd && $pass != "") {
                    $usertrue = 1;
                    return 1;
                }
            } else {
                list($pass, $last_ip) = $db->sql_fetchrow($db->sql_query("SELECT user_password, user_last_ip FROM ".$prefix."_users WHERE user_id = '".$uid."' AND user_name = '".$una."'"));
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
function is_user_id($name) {
    global $prefix, $db;
    $name = text_filter(substr($name, 0, 25));
    list($uid) = $db->sql_fetchrow($db->sql_query("SELECT user_id FROM ".$prefix."_users WHERE user_name = '".$name."'"));
    return intval($uid);
}

# Check admin
function is_admin($adm="") {
    global $prefix, $db, $admin;
    static $admintrue;
    if (!empty($admin)) {
        if (!isset($admintrue)) {
            $id = intval(substr($admin[0], 0, 11));
            $name = htmlspecialchars(substr($admin[1], 0, 25));
            $pwd = htmlspecialchars(substr($admin[2], 0, 40));
            $ip = getIp();
            if ($id && $name && $pwd && $ip) {
                list($aname, $apwd, $aip) = $db->sql_fetchrow($db->sql_query("SELECT name, pwd, ip FROM ".$prefix."_admins WHERE id = '".$id."'"));
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

# Check modul admin
function is_admin_modul($modul) {
    global $prefix, $db, $admin;
    $aid = intval(substr($admin[0], 0, 11));
    $modul = addslashes(trim(substr($modul, 0, 25)));
    static $modules;
    if (!is_array($modules)) {
        $result = $db->sql_query("SELECT mid, title FROM ".$prefix."_modules");
        while (list($mid, $title) = $db->sql_fetchrow($result)) $modules[] = array($mid, $title);
    }
    static $amodules;
    if (!is_array($amodules)) {
        list($amodules) = $db->sql_fetchrow($db->sql_query("SELECT modules FROM ".$prefix."_admins WHERE id = '".$aid."'"));
        $amodules = explode(",", $amodules);
    }
    foreach ($modules as $val) {
        if ($modul == $val[1] && $modul != "") {
            $admuser = 0;
            foreach ($amodules as $val2) {
                if ($val[0] == $val2) $admuser = 1;
            }
            if (is_admin_god() || $admuser == 1) return 1;
        }
    }
    return 0;
}

# Check moderator
function is_moder($modul="") {
    $modul = ($modul) ? addslashes(trim(substr($modul, 0, 25))) : 0;
    if ((is_admin() && is_admin_god()) || ($modul && is_admin() && is_admin_modul($modul))) {
        return 1;
    } else {
        return 0;
    }
}

# Search user name
function get_user() {
    global $prefix, $db;
    $let = analyze_name($_GET['term']);
    if ($let) {
        $result = $db->sql_query("SELECT user_name FROM ".$prefix."_users WHERE user_name LIKE '".$let."%' ORDER BY user_name ASC");
        while(list($user_name) = $db->sql_fetchrow($result)) $name[]= "\"".$user_name."\"";
        echo "[".implode(", ", $name)."]";
    }
}

# Autocomplete user name
function get_user_search() {
    global $conf;
    $arg = func_get_args();
    $class = empty($arg[3]) ? "sl_field" : "sl_field ".$arg[3];
    $req = empty($arg[4]) ? "" : " required";
    $cont = "<script>
    $(function() {
        $(\"#".$arg[0]."\").autocomplete({
            source: \"index.php?go=1&op=get_user\",
            minLength: ".$conf['slet']."
        });
    });
    </script>"
    ."<input type=\"text\" id=\"".$arg[0]."\" name=\"".$arg[0]."\" value=\"".$arg[1]."\" maxlength=\"".$arg[2]."\" class=\"".$class."\" placeholder=\""._NICKNAME."\"".$req.">";
    return $cont;
}

# Redirect referer
function referer($url) {
    $referer = getenv('HTTP_REFERER');
    if (isset($_REQUEST['refer']) && $referer != '' && !preg_match('#^unknown#i', $referer) && !preg_match('#^bookmark#i', $referer)) {
        header('Location: '.$referer);
    } else {
        header('Location: '.$url);
    }
}

# Analyze name
function analyze_name($name) {
    $name = ($name) ? ((preg_match("#\"|\'|\.|\:|\;|\/|\*#", $name)) ? "" : $name) : "";
    return $name;
}

# URL types
function url_types() {
    $arg = func_get_args();
    $url = explode(",", $arg['0']);
    $con = array();
    if (is_array($url)) {
        foreach($url as $v) {
            $var = parse_url($v);
            $scheme = (!empty($var['scheme'])) ? $var['scheme'] : "";
            if ($scheme == "ed2k") {
                $con[] = "eMule";
            } elseif ($scheme == "http") {
                $con[] = ucfirst(current(explode(".", str_replace("www.", "", $var['host']))));
            }
        }
        $types = is_array($con) ? implode(", ", array_unique($con)) : "";
        return $types;
    }
}

# Check user
function check_user() {
    global $user;
    if (is_user()) {
        $f = "config/counter/user.txt";
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
function head() {
    global $prefix, $db, $home, $index, $conf, $confs, $confr, $confse, $user, $admin, $name, $theme, $op;
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
            $db->sql_query("DELETE FROM ".$prefix."_session WHERE time < '".$past."'");
            unlink($sess_f);
            $fp = fopen($sess_f, "wb");
            fwrite($fp, $ctime);
            fclose($fp);
        }
        if (!empty($uname)) {
            if (!defined("ADMIN_FILE") && is_user()) {
                $uagent = getAgent();
                $uid= intval($user[0]);
                $db->sql_query("UPDATE ".$prefix."_users SET user_last_ip = '".$ip."', user_lastvisit = NOW(), user_agent = '".$uagent."' WHERE user_id = '".$uid."'");
            }
            $num = $db->sql_numrows($db->sql_query("SELECT id FROM ".$prefix."_session WHERE uname = '".$uname."'"));
            if ($num >= 1) {
                $db->sql_query('UPDATE '.$prefix.'_session SET time = :time, host_addr = :ip, guest = :guest, module = :module, url = :url WHERE uname = :uname', [':time' => $ctime, ':ip' => $ip, ':guest' => $guest, ':module' => $name, ':url' => $url, ':uname' => $uname]);
            } else {
                $db->sql_query("INSERT INTO ".$prefix."_session (uname, time, host_addr, guest, module, url) VALUES ('".$uname."', '".$ctime."', '".$ip."', '".$guest."', '".$name."', '".$url."')");
            }
        }
    }
    if ($confr['refer']) {
        $referer = get_referer();
        if ($referer) {
            $refer_f = "config/counter/refer.txt";
            $refer_t = (file_exists($refer_f) && filesize($refer_f) != 0) ? file_get_contents($refer_f) : 0;
            $past = $ctime - intval($confr['refer_t']);
            if ($refer_t < $past) {
                $db->sql_query("DELETE FROM ".$prefix."_referer WHERE lid = '0'");
                unlink($refer_f);
                $fp = fopen($refer_f, "wb");
                fwrite($fp, $ctime);
                fclose($fp);
            }
            $ip = getIp();
            $uid = intval($user[0]);
            $link = text_filter($request);
            if (is_active('auto_links')) {
                list($exist) = $db->sql_fetchrow($db->sql_query("SELECT ip FROM ".$prefix."_referer WHERE ip = '".$ip."' AND lid != '0'"));
                if ($exist) {
                    if ($confr['referb'] != 1 || ($confr['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".$prefix."_referer VALUES (NULL, '".$uid."', '".$uname."', '".$ip."', '".$referer."', '".$link."', NOW(), '0')");
                } else {
                    $result = $db->sql_query("SELECT link FROM ".$prefix."_auto_links");
                    while(list($slink) = $db->sql_fetchrow($result)) {
                        if (preg_match("#".$slink."#i", $referer)) {
                            $islink = 1;
                            break;
                        } else {
                            $islink = 0;
                        }
                    }
                    if ($islink) {
                        $db->sql_query("UPDATE ".$prefix."_auto_links SET hits = hits+1 WHERE link = '".$slink."'");
                        list($lid) = $db->sql_fetchrow($db->sql_query("SELECT id FROM ".$prefix."_auto_links WHERE link = '".$slink."'"));
                        $db->sql_query("INSERT INTO ".$prefix."_referer VALUES (NULL, '".$uid."', '".$uname."', '".$ip."', '".$referer."', '".$link."', NOW(), '".$lid."')");
                    } else {
                        if ($confr['referb'] != 1 || ($confr['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".$prefix."_referer VALUES (NULL, '".$uid."', '".$uname."', '".$ip."', '".$referer."', '".$link."', NOW(), '0')");
                    }
                }
            } else {
                if ($confr['referb'] != 1 || ($confr['referb'] == 1 && from_bot())) $db->sql_query("INSERT INTO ".$prefix."_referer VALUES (NULL, '".$uid."', '".$uname."', '".$ip."', '".$referer."', '".$link."', NOW(), '0')");
            }
        }
    }
    include('config/config_stat.php');
    if ($confst['stat']) {
        $sreferer = get_referer();
        $sreqhom = text_filter($request);
        $spath = 'config/counter/';
        $sdate = file($spath.'stat.txt');
        if ($sdate) {
            $con = explode('|', trim($sdate[0]));
            if (date('d.m.Y') != $con[0]) {
                $fpd = fopen($spath.'days.txt', 'ab');
                flock($fpd, 2);
                fwrite($fpd, $sdate[0]."\r\n");
                flock($fpd, 3);
                fclose($fpd);
                if (file_exists($spath.'stat.txt')) unlink($spath.'stat.txt');
                if (file_exists(COUNTER_DIR.'/ips.log')) unlink(COUNTER_DIR.'/ips.log');
                if (file_exists($spath.'user.txt')) unlink($spath.'user.txt');
                if (substr($con[0], 3) != date('m.Y')) {
                    $month = date('Y-m', strtotime('-1 month'));
                    rename($spath.'days.txt', $spath.'stat/stat_'.$month.'.txt');
                    if (file_exists($spath.'days.txt')) unlink($spath.'days.txt');
                }
                $ahits = ($con[3]) ? ($con[3]+1) : '1';
                $sengine = ($conf['session'] && !empty($guest) == 1) ? '1' : '0';
                $srefer = ($sreferer) ? '1' : '0';
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
                $wc = date('d.m.Y').'|0|1|'.$ahits.'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            } else {
                $check = checkUniqueIp();
                $checku = check_user();
                $shost = ($check) ? intval($con[1]+1) : $con[1];
                $sengine = ($check && $conf['session'] && $guest == 1) ? intval($con[4]+1) : $con[4];
                $srefer = ($check && $sreferer) ? intval($con[5]+1) : $con[5];
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? intval($con[6]+1) : $con[6];
                $suser = ($checku && $conf['session'] && $guest == 2) ? intval($con[7]+1) : $con[7];
                $wc = $con[0].'|'.$shost.'|'.intval($con[2]+1).'|'.intval($con[3]+1).'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|'.$suser;
            }
            $fps = fopen($spath.'stat.txt', 'wb');
            if (flock($fps, LOCK_EX)) {
                ftruncate($fps, 0);
                fwrite($fps, $wc);
                fflush($fps);
                flock($fps, LOCK_UN);
            }
            fclose($fps);
        } elseif (!file_exists($spath.'stat.txt') || (date('d.m.Y', filemtime($spath.'stat.txt')) < date('d.m.Y', time()))) {
            unlink(COUNTER_DIR.'/ips.log');
            unlink($spath.'user.txt');
            $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
            $srefer = ($sreferer) ? '1' : '0';
            $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
            $wc = date('d.m.Y').'|0|1|1|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            $fps = fopen($spath.'stat.txt', 'wb');
            flock($fps, 2);
            fwrite($fps, $wc);
            flock($fps, 3);
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
    setThemeInclude();
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
        $atime = date('Y-m-d H:i:s');
        $meta = getUrlMeta();
        $time = empty($meta['1']) ? $atime : $meta['1'];
        $mtime = empty($meta['2']) ? $atime : $meta['2'];
        $title = empty($meta['3']) ? $conf['sitename'] : $meta['3'];
        $desc = empty($meta['4']) ? $conf['slogan'] : $meta['4'];
        $keys = empty($meta['5']) ? $conf['keys'] : $meta['5'];
        $img = empty($meta['6']) ? $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'] : $conf['homeurl'].'/'.$meta['6'];
        $ctitle = empty($meta['7']) ? '0' : $meta['7'];
        $cdesc = empty($meta['8']) ? '0' : $meta['8'];
        $cimg = empty($meta['9']) ? '0' : $meta['9'];
        $url = ($conf['rewrite']) ? urldecode(substr($request, 1)) : urldecode(str_replace('index.php?', '', substr($request, 1)));
        $purl = ($conf['rewrite']) ? $conf['homeurl'].'/'.htmlspecialchars($url) : (($home) ? $conf['homeurl'] : $conf['homeurl'].'/index.php?'.htmlspecialchars($url));
        $type = 'article';
        if ($home) {
            $title = $conf['sitename'].' '.$sep.' '.$conf['slogan'];
        } else {
            if ($conf['ltitle']) {
                $mod = deflmconst($conf['name']);
                $title = ($title == $conf['sitename']) ? array() : array($title);
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
        .'<meta name="author" content="'.$conf['sitename'].'">'."\n";
        $keys = explode(',', $keys);
        if ($conf['kmix']) shuffle($keys);
        $conf['keys'] = $keys;
        $keys = ($conf['ksep']) ? implode(', ', array_map('trim', $keys)) : implode(' ', array_map('trim', $keys));
        $strmeta .= '<meta name="keywords" content="'.$keys.'">'."\n"
        .'<meta name="description" content="'.$desc.'">'."\n"
        .'<meta name="robots" content="index, follow">'."\n"
        .'<meta name="revisit-after" content="1 days">'."\n"
        .'<meta name="rating" content="general">'."\n"
        .'<meta name="generator" content="SLAED CMS">'."\n";
        if ($confse['agraph'] && !empty($confse['graph'])) {
            $from = array('[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]');
            $to = array($conf['homeurl'], $conf['sitename'], $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'], _LOCALE, $time, $mtime, $title, $desc, $img, $ctitle, $type, $purl);
            $strmeta .= str_replace($from, $to, $confse['graph']);
        }
        $strlink .= '<link rel="shortcut icon" href="templates/'.$theme.'/favicon.png">'."\n";
        if (strpos($conf['homeurl'], get_host()) !== false) {
            $strlink .= '<link rel="canonical" href="'.$purl.'">'."\n";
        }
        include('config/config_rss.php');
        if ($confrs['act']) {
            $fieldc = explode('||', $confrs['rss']);
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
    if (!defined('ADMIN_FILE') && $confse['aschema'] && !empty($confse['schema'])) {
        $from = array('[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]');
        $to = array($conf['homeurl'], $conf['sitename'], $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'], _LOCALE, $time, $mtime, $title, $desc, $img, $ctitle, $type, $purl);
        $stscript = str_replace($from, $to, $confse['schema']);
    }
    $script = (defined('ADMIN_FILE') || empty($conf['script_b'])) ? doScript()."\n".$stscript : $stscript;
    $head = str_replace(array('{%META%}', '{%LINK%}', '{%SCRIPT%}'), array($strmeta, $strlink, $script), addblocks($head));
    $cron = 0;
    if ($confs['log_d']) {
        $sess_f = 'config/counter/dump.txt';
        $sess_d = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($confs['sess_d']);
        if ($sess_d < $past) {
            $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'filereport', 'go=3&amp;op=filereport', ''); return false;\"$1>", $head);
            $cron = 1;
        } else {
            $cron = 0;
        }
    }
    if ($confs['log_b'] && !$cron) {
        $sess_f = COUNTER_DIR.'/backup.log';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($confs['sess_b']);
        if ($sess_b < $past) {
            $head = preg_replace("#<body(.*?)>#si", "<body OnLoad=\"AjaxLoad('GET', '0', 'backup', 'go=3&amp;op=backup', ''); return false;\"$1>", $head);
            $cron = 1;
        } else {
            $cron = 0;
        }
    }
    include('config/sitemap.php');
    if ($confma['auto'] && !$cron) {
        $sess_f = 'sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = $ctime - intval($confma['auto_t']);
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
function foot() {
    global $home, $name, $index, $conf, $confs, $do_gzip_compress;
    $index = addblocks($index);
    $index = (!defined('ADMIN_FILE') && !empty($conf['script_b'])) ? str_replace('{%SCRIPT%}', doScript(), $index) : str_replace('{%SCRIPT%}', '', $index);
    echo setTemplateFoot($index);
    unset($index);
    if (!defined('ADMIN_FILE') && $conf['rewrite']) rewrite();
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

# Log files report
function create_dump($dir, &$log) {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..') continue;
                $location = $dir.$file;
                if (filetype($location) == 'dir') {
                    create_dump($location.'/', $log);
                } else {
                    $log[$location] = md5_file($location);
                }
            }
            closedir($dh);
        }
    }
}

function write_dump($dump, $file) {
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

function write_log($log, $file) {
    global $confs;
    if ($fp = fopen($file, "ab")) {
        if (filesize($file) > $confs['log_size']) {
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

function diff_dump($dump, $old) {
    $log = array();
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

function filereport() {
    global $conf, $confs;
    if ($confs['log_d']) {
        $sess_f = "config/counter/dump.txt";
        $sess_d = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = time() - intval($confs['sess_d']);
        if ($sess_d < $past) {
            unlink($sess_f);
            $fp = fopen($sess_f, "wb");
            fwrite($fp, time());
            fclose($fp);
            
            $safe = ini_get("safe_mode") == "1" ? 1 : 0;
            if (!$safe && function_exists("set_time_limit")) set_time_limit(600);

            $dump = array();
            create_dump("./", $dump);
            if (file_exists("config/logs/dump.txt") && filesize("config/logs/dump.txt") != 0) {
                if ($log = diff_dump($dump, file("config/logs/dump.txt"))) sort($log);
            } else {
                $log = false;
            }
            write_log($log, "config/logs/dump_log.txt");
            write_dump($dump, "config/logs/dump.txt");
            if ($confs['mail_d']) {
                $log = ($log) ? implode("<br>", $log) : _NO;
                $subject = $conf['sitename']." - "._SECURITY;
                $mmsg = $conf['sitename']." - "._SECURITY."<br><br>".$log."<br>"._DATE.": ".date(_TIMESTRING);
                mail_send($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
            }
        }
    }
}

# User and admin login report
function login_report($id, $typ, $login, $pass) {
    global $admin, $user, $confs;
    $id = ($id) ? "admin" : "user";
    if (($confs['log_a'] && $id) || ($confs['log_u'] && !$id)) {
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
            if (filesize($path) > $confs['log_size']) {
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
	global $confs, $confdb, $db;
	
	if (!$confs['log_b']) {
		return false;
	}
	
	// Logging-Start für Performance-Analyse
	$backup_start = microtime(true);
	
	$sess_f = COUNTER_DIR.'/backup.log';
	$sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
	$past = time() - intval($confs['sess_b']);
	
	if ($sess_b >= $past) {
		return false; // Noch nicht Zeit für Backup
	}
	
	// Timestamp-Datei aktualisieren
	if (file_exists($sess_f)) @unlink($sess_f);
	$fp_time = @fopen($sess_f, "wb");
	if ($fp_time) {
		fwrite($fp_time, time());
		fclose($fp_time);
	}
	
	// FIX: Memory-Management
	@ini_set('memory_limit', '512M');
	
	// safe_mode ist entfernt; defensiv behandeln
	$safe = 0;
	if (function_exists('ini_get')) {
		$sm = @ini_get('safe_mode');
		$safe = ($sm && $sm != '0') ? 1 : 0;
	}
	if (!$safe && function_exists("set_time_limit")) @set_time_limit(600);
	
	# Кодировка соединения с MySQL
	# auto - автоматический выбор (устанавливается кодировка таблицы), latin1, cp1251, utf8 и т.п.
	$ccharset = "auto";

	# Типы таблиц у которых сохраняется только структура, разделенные запятой
	$conlycreate = "MRG_MyISAM,MERGE,HEAP,MEMORY";

	# В фильтре таблиц указываются специальные шаблоны по которым отбираются таблицы. В шаблонах можно использовать следующие специальные символы:
	# символ * — означает любое количество символов;
	# символ ? — означает один любой символ;
	# символ ^ — означает исключение из списка таблицы или таблиц.

	# Примеры:
	# slaed_* все таблицы начинающиеся с "slaed_" (все таблицы форума invision board)
	# slaed_*, ^slaed_session все таблицы начинающиеся с "slaed_", кроме "slaed_session"
	# slaed_s*s, ^slaed_session все таблицы начинающиеся с "slaed_s" и заканчивающиеся буквой "s", кроме "slaed_session"
	# ^*s все таблицы, кроме таблиц заканчивающихся буквой "s"
	# ^slaed_???? все таблицы, кроме таблиц, которые начинаются с "slaed_" und содержат 4 символа после знака подчеркивания
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
	$tbls = array();
	
	if (!empty($ctables)) {
		foreach($btables as $table) {
			$table = preg_replace("/[^\w*?^]/", "", $table);
			$pattern = array("/\?/", "/\*/");
			$replace = array(".", ".*?");
			$tbls[] = preg_replace($pattern, $replace, $table);
		}
	}

	// Zeichenkodierung setzen, wenn nicht auto
	if ($bmysql_ver > 40101 && $ccharset != 'auto') {
		$db->sql_query("SET NAMES '".$ccharset."'");
		$last_charset = $ccharset;
	} else {
		$last_charset = "";
	}

	// FIX: Korrigierte Filter-Logik
	$tables = array();
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
				// Exclude-Modus: Nimm alles außer status == -1
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
	$tabinfo = array();
	$tab_charset = array();
	$tab_type = array();
	$tabsize = array();
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

	// FIX: Path Traversal Sicherheitslücke
	$safe_dbname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $confdb['name']);
	$name = $safe_dbname."_".date("Y-m-d_H-i-s");
	
	// FIX: Verzeichnis-Check
	$backup_dir = BACKUP_DIR.'/';
	if (!is_dir($backup_dir)) {
		if (!@mkdir($backup_dir, 0750, true)) {
			error_log("Backup failed: Cannot create backup directory");
			return false;
		}
	}
	
	$filepath = $backup_dir.$name.'.sql';
	
	// FIX: Error-Handling für fopen
	$fp = @fopen($filepath, "wb");
	if (!$fp) {
		error_log("Backup failed: Cannot create file " . $filepath);
		return false;
	}
	
	// Header schreiben
	fwrite($fp, "# DB: ".$confdb['name']."\n");
	fwrite($fp, "# Tables: ".$tabs."\n");
	fwrite($fp, "# Size: ".round($bsize / 1048576, 2)." MB\n");
	fwrite($fp, "# Lines: ".number_format($tabinfo[0], 0, ",", " ")."\n");
	fwrite($fp, "# Date: ".date("Y.m.d H:i:s")."\n\n");

	$db->sql_query("SET SQL_QUOTE_SHOW_CREATE = 1");

	foreach ($tables as $table) {
		// FIX: Charset isset() Check
		if ($bmysql_ver > 40101 && isset($tab_charset[$table]) && $tab_charset[$table] != $last_charset) {
			if ($ccharset == "auto" && !empty($tab_charset[$table])) {
				$db->sql_query("SET NAMES '".$tab_charset[$table]."'");
				$last_charset = $tab_charset[$table];
			}
		}

		$res = $db->sql_query("SHOW CREATE TABLE `{$table}`");
		$tab = $res->fetch(PDO::FETCH_NUM);
		
		// Для MariaDB 10+ НЕ используем условные комментарии
		if (isset($tab[1])) {
			fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n{$tab[1]};\n\n");
		}

		if (in_array($tab_type[$table], $bonly_create)) continue;

		$NumericColumn = array();
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
			
			while ($res = $db->sql_query("SELECT * FROM `{$table}` LIMIT {$from}, {$limit}")) {
				$countThisBatch = 0;
				
				while ($row = $res->fetch(PDO::FETCH_NUM)) {
					$countThisBatch++;
					$i++;
					
					// ✅ КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Проверяем разделение ПЕРЕД записью строки
					if ($i > 1 && ($i - 1) % 10000 == 0) {
						// Закрываем предыдущий INSERT и начинаем новый
						fwrite($fp, ";\n\nINSERT INTO `{$table}` VALUES");
					}
					
					for ($k = 0; $k < $fields; $k++) {
						if ($NumericColumn[$k]) {
							$row[$k] = isset($row[$k]) ? $row[$k] : "NULL";
						} else {
							$row[$k] = isset($row[$k]) ? $db->sqlconnid->quote($row[$k]) : "NULL";
						}
					}
					
					// Добавляем запятую ПЕРЕД строкой (кроме первой и после разделения)
					$is_first_in_block = ($i == 1) || (($i - 1) % 10000 == 0);
					fwrite($fp, ($is_first_in_block ? "\n" : ",\n")."(".implode(",", $row).")");
				}
				
				if ($countThisBatch < $limit) break;
				$from += $limit;
			}
			
			fwrite($fp, ";\n\n");
		}
	}

	fclose($fp);
	addCompress($backup_dir, $filepath, $name, 'auto', true);
	
	// Performance-Logging
	$duration = round(microtime(true) - $backup_start, 2);
	error_log("Backup completed: {$tabs} tables, ".round($bsize/1048576, 2)."MB in {$duration}s");
	return true;
}

# Check user acess
function is_acess($ids) {
    global $prefix, $db, $user, $conf;
    if ($ids) {
        $id = explode("|", $ids);
        if (is_moder(isset($conf['name']))) {
            $isa = true;
        } elseif (is_user() && $id[1]) {
            $uid = intval($user[0]);
            $mid = explode(",", $id[1]);
            foreach ($mid as $val) if ($val) $dmid[] = "g.id=".$val;
            $dmid = implode(" OR ", $dmid);
            list($uid) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(u.user_id) FROM ".$prefix."_users AS u LEFT JOIN ".$prefix."_groups AS g ON ((g.extra = 1 AND u.user_group = g.id) OR (g.extra != 1 AND u.user_points >= g.points)) WHERE u.user_id = '".$uid."' AND (".$dmid.")"));
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
function getcat() {
    global $prefix, $db, $conf;
    $arg = func_get_args();
    $mod = analyze($arg[0]);
    $conf['name'] = isset($conf['name']) ? $conf['name'] : $mod;
    $id = intval($arg[1]);
    $class = ($arg[3]) ? "sl_field ".$arg[3] : "sl_field";
    $where = ($mod) ? "WHERE modul = '".$mod."' ORDER BY ordern" : "ORDER BY ordern";
    $result = $db->sql_query("SELECT id, title, parentid, auth_view FROM ".$prefix."_categories ".$where);
    if ($db->sql_numrows($result) > 0) {
        $content = (empty($arg[5])) ? "<select name=\"".$arg[2]."\" title=\""._CATEGORIES."\" class=\"".$class."\">" : "";
        while (list($cid, $title, $parentid, $auth_view) = $db->sql_fetchrow($result)) if (is_acess($auth_view)) $massiv[$cid] = array(defconst($title), $parentid);
        foreach ($massiv as $key => $val) {
            $cont[$key] = $val[0];
            $flag = $val[1];
            while ($flag != 0) {
                $cont[$key] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$cont[$key];
                $flag = intval($massiv[$flag][1]);
            }
            $sel = ($id == $key) ? " selected" : "";
            $content .= "<option value=\"".$key."\"".$sel.">".$cont[$key]."</option>";
        }
        $rcont = (empty($arg[5])) ? $content."</select>" : $content;
        return $rcont;
    } elseif ($arg[4]) {
        return "<select name=\"".$arg[2]."\" title=\""._CATEGORIES."\" class=\"".$class."\">".$arg[4]."</select>";
    }
}

# Format categories links
function catlink() {
    global $prefix, $db, $conf, $confse;
    $arg = func_get_args();
    $mod = analyze($arg[0]);
    $id = intval($arg[1]);
    $sep = ($arg[2]) ? " ".urldecode($arg[2])." " : " ".urldecode($conf['defis'])." ";
    $content = ($arg[3]) ? "<a href=\"index.php?name=".$conf['name']."\" title=\"".$arg[3]."\">".$arg[3]."</a>".$sep : "";
    $where = ($mod) ? "WHERE modul = '".$mod."'" : "";
    $result = $db->sql_query("SELECT id, title, parentid FROM ".$prefix."_categories ".$where);
    if ($db->sql_numrows($result) > 0) {
        while (list($cid, $title, $parentid) = $db->sql_fetchrow($result)) $massiv[$cid] = array(defconst($title), $parentid);
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
}

# Format categories IDs
function catids() {
    global $prefix, $db, $conf;
    $arg = func_get_args();
    $mod = analyze($arg[0]);
    $id = intval($arg[1]);
    $content = "";
    $where = ($mod) ? "WHERE modul = '".$mod."'" : "";
    $result = $db->sql_query("SELECT id, parentid FROM ".$prefix."_categories ".$where);
    if ($db->sql_numrows($result) > 0) {
        while (list($cid, $parentid) = $db->sql_fetchrow($result)) $massiv[$cid] = array($parentid);
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
}

# Format categories IDs from module
function catmids() {
    global $prefix, $db, $conf, $currentlang;
    $arg = func_get_args();
    $where = ($conf['multilingual']) ? "WHERE modul = '".$arg[0]."' AND (language = '".$currentlang."' OR language = '')" : "WHERE modul = '".$arg[0]."'";
    $result = $db->sql_query("SELECT id, auth_read FROM ".$prefix."_categories ".$where." ORDER BY id");
    while (list($cid, $auth_read) = $db->sql_fetchrow($result)) if (is_acess($auth_read)) $catid[] = $cid;
    $where = ($catid) ? "AND ".$arg[1]." IN (".implode(", ", $catid).")" : "";
    return $where;
}

# Length end filter
function cutstr($strip, $size, $type='') {
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
function is_active($mod, $view='') {
    global $prefix, $db;
    static $name;
    if (!is_array($name)) {
        $where = isset($view) ? " AND view = '".intval($view)."'" : "";
        $result = $db->sql_query("SELECT title FROM ".$prefix."_modules WHERE active = '1'".$where."");
        while (list($title) = $db->sql_fetchrow($result)) $name[] = $title;
    }
    foreach ($name as $val) {
        if ($val == $mod) {
            $a = 1;
            break;
        } else {
            $a = 0;
        }
    }
    return $a;
}

# Rewrite mod
function rewrite() {
    $contents = ob_get_clean();
    include("config/config_rewrite.php");
    $rewrite = preg_replace($in, $out, $contents);
    echo $rewrite;
}

# Decode BB
function bb_decode($sourse, $mod, $id="") {
    $mod = (empty($mod)) ? "all" : strtolower($mod);
    $bb = array();
    $html = array();
    $bb[] = "#\[img\]([^?](?:[^\[]+|\[(?!url))*?)\[/img\]#i";
    $html[] = "<img src=\"\\1\" alt=\"\\1\" title=\"\\1\" class=\"sl_img\">";
    $bb[] = "#\[img=([a-zA-Z]+)\]([^?](?:[^\[]+|\[(?!url))*?)\[/img\]#si";
    $html[] = "<img src=\"\\2\" style=\"float: \\1;\" alt=\"\\2\" title=\"\\2\" class=\"sl_img\">";
    $bb[] = "#\[img\ alt=([\pL0-9\_\-\.\"\s]+)\]([^?](?:[^\[]+|\[(?!url))*?)\[/img\]#siu";
    $html[] = "<img src=\"\\2\" style=\"float: \\1;\" alt=\"\\1\" title=\"\\1\" class=\"sl_img\">";
    $bb[] = "#\[img=([a-zA-Z]+) alt=([\pL0-9\_\-\.\"\s]+)\]([^?](?:[^\[]+|\[(?!url))*?)\[/img\]#siu";
    $html[] = "<img src=\"\\3\" style=\"float: \\1;\" alt=\"\\2\" title=\"\\2\" class=\"sl_img\">";
    $bb[] = "#\[url\](ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?)\[/url\]#si";
    $html[] = "eMule/eDonkey: <a href=\"\\1\" target=\"_blank\" title=\"\\2\">\\2</a>";
    $bb[] = "#\[url=(ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?)\](.*?)\[/url\]#si";
    $html[] = "<a href=\"\\1\" target=\"_blank\" title=\"\\2\">\\4</a>";
    $bb[] = "#\[url\](ed2k://\|server\|([\d\.]+?)\|(\d+?)\|/?)\[/url\]#si";
    $html[] = "ed2k Server: <a href=\"\\1\" target=\"_blank\" title=\"\\2\">\\2</a> - Port: \\3";
    $bb[] = "#\[url=(ed2k://\|server\|[\d\.]+\|\d+\|/?)\](.*?)\[/url\]#si";
    $html[] = "<a href=\"\\1\" target=\"_blank\" title=\"\\2\">\\2</a>";
    $bb[] = "#\[url\](ed2k://\|friend\|(.*?)\|[\d\.]+\|\d+\|/?)\[/url\]#si";
    $html[] = "Friend: <a href=\"\\1\" target=\"_blank\" title=\"\\2\">\\2</a>";
    $bb[] = "#\[url=(ed2k://\|friend\|(.*?)\|[\d\.]+\|\d+\|/?)\](.*?)\[/url\]#si";
    $html[] = "<a href=\"\\1\" target=\"_blank\" title=\"\\3\">\\3</a>";
    $bb[] = "#\[url\]([\w]+?://([\w\#$%&~/.\-;:=,?@\]+]+|\[(?!url=))*?)\[/url\]#si";
    $html[] = "<a href=\"\\1\" target=\"_blank\" title=\"\\1\">\\1</a>";
    $bb[] = "#\[url\]((www|ftp)\.([\w\#$%&~/.\-;:=,?@\]+]+|\[(?!url=))*?)\[/url\]#si";
    $html[] = "<a href=\"http://\\1\" target=\"_blank\" title=\"\\1\">\\1</a>";
    $bb[] = "#\[url=([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*?)\]([^?\n\r\t].*?)\[/url\]#si";
    $html[] = "<a href=\"\\1\" target=\"_blank\" title=\"\\1\">\\2</a>";
    $bb[] = "#\[url=((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*?)\]([^?\n\r\t].*?)\[/url\]#si";
    $html[] = "<a href=\"http://\\1\" target=\"_blank\" title=\"\\1\">\\3</a>";
    $bb[] = "#\[mail\](\S+?)\[/mail\]#i";
    $html[] = "<a href=\"mailto:\\1\">\\1</a>";
    $bb[] = "#\[mail\s*=\s*([\.\w\-]+\@[\.\w\-]+\.[\w\-]+)\s*\](.*?)\[\/mail\]#i";
    $html[] = "<a href=\"mailto:\\1\">\\2</a>";
    $bb[] = "#\[color=(\#[0-9A-F]{6}|[a-z]+)\](.*?)\[/color\]#si";
    $html[] = "<span style=\"color: \\1\">\\2</span>";
    $bb[] = "#\[family=([A-Za-z ]+)\](.*?)\[/family\]#si";
    $html[] = "<span style=\"font-family: \\1\">\\2</span>";
    $bb[] = "#\[size=([0-9]{1,2}+)\](.*?)\[/size\]#si";
    $html[] = "<span style=\"font-size: \\1px\">\\2</span>";
    $bb[] = "#\[(left|right|center|justify)\](.*?)\[/\\1\]#si";
    $html[] = "<div style=\"text-align: \\1;\">\\2</div>";
    $bb[] = "#\[b\](.*?)\[/b\]#si";
    $html[] = "<b>\\1</b>";
    $bb[] = "#\[i\](.*?)\[/i\]#si";
    $html[] = "<i>\\1</i>";
    $bb[] = "#\[u\](.*?)\[/u\]#si";
    $html[] = "<u>\\1</u>";
    $bb[] = "#\[s\](.*?)\[/s\]#si";
    $html[] = "<s>\\1</s>";
    $bb[] = "#\[li\]#si";
    $html[] = "&bull; ";
    $bb[] = "#\[hr\]#si";
    $html[] = "<hr>";
    $bb[] = "#\*(\d{2})#";
    $html[] = "<img src=\"".img_find("smilies/\\1.gif")."\" alt=\""._SMILIE." - \\1\" title=\""._SMILIE." - \\1\">";

    $sourse = str_replace(array("&#034;", "&#039;"), array("\"", "'"), preg_replace($bb, $html, $sourse));
    # $sourse = preg_replace($bb, $html, $sourse);
    
    while (preg_match("#\[quote\](.*?)\[/quote\]#si", $sourse)) $sourse = preg_replace_callback("#\[quote\](.*?)\[/quote\]#si", "encode_quote", $sourse);
    while (preg_match("#\[hide\](.*?)\[/hide\]#si", $sourse)) $sourse = preg_replace_callback("#\[hide\](.*?)\[/hide\]#si", "encode_hide", $sourse);
    if (empty($id)) {
        while (preg_match("#\[code=(.*?)\](.*?)\[/code\]#si", $sourse)) $sourse = preg_replace_callback("#\[code=(.*?)\](.*?)\[/code\]#si", "encode_php", $sourse);
        while (preg_match("#\[code\](.*?)\[/code\]#si", $sourse)) $sourse = preg_replace_callback("#\[code\](.*?)\[/code\]#si", "encode_code", $sourse);
        while (preg_match("#\[php\](.*?)\[/php\]#si", $sourse)) $sourse = preg_replace_callback("#\[php\](.*?)\[/php\]#si", "encode_php", $sourse);
    }
    while (preg_match("#\[usehtml\](.*?)\[/usehtml\]#si", $sourse)) $sourse = preg_replace_callback("#\[usehtml\](.*?)\[/usehtml\]#si", "use_html", $sourse);
    while (preg_match("#\[usephp\](.*?)\[/usephp\]#si", $sourse)) $sourse = preg_replace_callback("#\[usephp\](.*?)\[/usephp\]#si", "use_php", $sourse);
    while (preg_match("#\[tabs=(.*?)\](.*?)\[/tabs\]#si", $sourse)) $sourse = preg_replace_callback("#\[tabs=(.*?)\](.*?)\[/tabs\]#si", "encode_tabs", $sourse);
    if (stripos($sourse, "[attach=") !== false) $sourse = encode_attach($sourse, $mod);
    
    $sourse = search_replace($sourse, $mod);
    return $sourse;
}

# Format tabs
function encode_tabs($text) {
    $num = isset($text[1]) ? intval($text[1]) : 0;
    $rep = isset($text[2]) ? trim($text[2]) : 0;
    $count = preg_match_all("#\[tab=([\pL0-9\_\-\.\"\s]+)\](.*?)\[/tab\]#siu", $rep, $date);
    for ($i = 0; $i < $count; $i++) {
        $title[] = $date[1][$i];
        $test[] = $date[2][$i];
    }
    $tabs = getNaviTabs($num, "tab", $title, $test);
    return $tabs;
}

# Format quote
function encode_quote($text) {
    return tpl_eval("quote", _QUOTE, $text[1]);
}

# Format hide
function encode_hide($text) {
    $text = (defined("ADMIN_FILE") || is_user()) ? tpl_eval("hide", _HIDE, $text[1]) : tpl_eval("hide", _HIDE, _HIDETEXT);
    return $text;
}

# Format code
function encode_code($text) {
    return tpl_eval("code", _CODE, str_replace("?", "&#063;", $text[1]));
}

# Format PHP code
function encode_php($text) {
    global $conf;
    static $sname;
    
    $replace = isset($text[2]) ? trim($text[2]) : trim($text[1]);
    $cname = isset($text[2]) ? analyze($text[1]) : 'php';
    
    $from = array('bash', 'cpp', 'csharp', 'css', 'delphi', 'diff', 'groovy', 'java', 'jscript', 'php', 'plain', 'python', 'ruby', 'scala', 'sql', 'vb', 'xml');
    $to = array('Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml');
    $cname = str_ireplace($from, $to, $cname);
    $ucname = strtolower($cname);
    
    $in = array('&#034;', '&quot;', '&#036;', '&dollar;', '&#038;', '&amp;', '&#039;', '&apos;', '&#060;', '&lt;', '&#062;', '&gt;', '&#092;', '&bsol;');
    $out = array("\"", "\"", "$", "$", "&", "&", "'", "'", "<", "<", ">", ">", "\\", "\\");
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
        $replace = explode("\n", str_replace(array("\r\n", "\r"), "\n", $replace));
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
    return tpl_eval("code", $cname." - "._CODE, $format);
}

# Format use HTML
function use_html($str) {
    return htmlspecialchars_decode(replace_break($str[1]), ENT_QUOTES);
}

# Format use PHP
function use_php($str) {
    global $conf;
    $in = array("&#036;", "&#092;");
    $out = array("$", "\\");
    $rep = str_replace($in, $out, $str[1]);
    ob_start();
    eval(htmlspecialchars_decode(replace_break($rep), ENT_QUOTES));
    $con = ob_get_clean();
    return $con;
}

# Format attach
function encode_attach($sourse, $mod) {
    include("config/config_uploads.php");
    include("config/config_templ.php");
    if (stripos($sourse, "rel=") && stripos($sourse, "width=")) {
        $match_count = preg_match_all("#\[attach=([a-zA-Z0-9\_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9\_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+) rel=([a-zA-Z0-9\_\-]+)\]#siu", $sourse, $date);
    } elseif (stripos($sourse, "width=")) {
        $match_count = preg_match_all("#\[attach=([a-zA-Z0-9\_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9\_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+)\]#siu", $sourse, $date);
    } else {
        $match_count = preg_match_all("#\[attach=([a-zA-Z0-9\_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9\_\-\.\"\s]+)\]#siu", $sourse, $date);
    }
    $con = explode("|", $confup[$mod]);
    $file = '';
    $text ='';
    $ftype = array("png", "jpg", "jpeg", "gif", "bmp");
    for ($i = 0; $i < $match_count; $i++) {
        $type = strtolower(substr(strrchr($date[1][$i], "."), 1));
        $file = "uploads/".$mod."/".$date[1][$i];
        if (in_array($type, $ftype)) {
            $tfile = "uploads/".$mod."/thumb/".$date[1][$i];
            $dtfile = "uploads/".$mod."/thumb";
            if ($mod != "" && file_exists($file) && !file_exists($tfile)) {
                if (!file_exists($dtfile)) mkdir($dtfile);
                $thumb = create_img_gd($file, $tfile, $con[6]);
                $timg = ($thumb) ? $tfile : $file;
            } else {
                $timg = $tfile;
            }
            if (file_exists($file)) list($width, $height) = getimagesize($file);
        } else {
            $width = $date[4][$i];
            $height = $date[5][$i];
        }
        $temp = $conftp[$type];
        $temp = str_replace("[src]", $file, $temp);
        $temp = str_replace("[tsrc]", $timg, $temp);
        $temp = (!empty($width) && intval($width)) ? str_replace("[width]", $width, $temp) : str_replace("[width]", $confup['width'], $temp);
        $temp = str_replace("[twidth]", $con[6], $temp);
        $temp = (!empty($height) && intval($height)) ? str_replace("[height]", $height, $temp) : str_replace("[height]", $confup['height'], $temp);
        $temp = str_replace("[align]", $date[2][$i], $temp);
        $temp = str_replace("[title]", $date[3][$i], $temp);
        $temp = str_replace("[quot]", "&quot;", $temp);
        $temp = (!empty($date[6][$i])) ? str_replace("[rel]", $date[6][$i], $temp) : str_replace("[rel]", "alternate", $temp);
        $cont[] = $temp;
        $text = preg_replace($date[0], $cont, $sourse);
    }
    $sourse = str_replace(array("[", "]"), "", $text);
    return $sourse;
}

# Search and replace
function search_replace($sourse, $mod) {
    global $confre;
    $mod = ($mod && isset($confre[$mod])) ? $confre[$mod] : "";
    if ($mod) {
        $mod = explode("||", $mod);
        foreach ($mod as $word) {
            if ($word != "") {
                $warray = explode("|", $word);
                if ($warray[0]) {
                    preg_match_all("#<[^>]*>#", $sourse, $tags);
                    array_unique($tags);
                    $taglist = array();
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
function addmail() {
    global $prefix, $db, $conf, $confu, $currentlang;
    $arg = func_get_args();
    $mod = analyze($arg[1]);
    if ($arg[0] && $mod) {
        $subject = (isset($arg[4]) == 1) ? $conf['sitename']." - ".$arg[3]." - "._COMMENT : $conf['sitename']." - ".$arg[3];
        $puname = ($arg[2]) ? text_filter(substr($arg[2], 0, 25)) : $confu['anonym'];
        $message = (isset($arg[4]) == 1) ? str_replace("[text]", sprintf(_ADDMAILC, $puname, $arg[3], $arg[5]), $conf['mtemp']) : str_replace("[text]", sprintf(_ADDMAIL, $puname, $arg[3]), $conf['mtemp']);
        list($mid) = $db->sql_fetchrow($db->sql_query("SELECT mid FROM ".$prefix."_modules WHERE title = '".$mod."'"));
        $wlang = ($conf['multilingual']) ? "AND (lang = '".$currentlang."' OR lang = '')" : "";
        $result = $db->sql_query("SELECT email, super, modules FROM ".$prefix."_admins WHERE smail = '1' ".$wlang." ORDER BY id");
        while (list($email, $super, $modules) = $db->sql_fetchrow($result)) {
            if ($super) {
                mail_send($email, $conf['adminmail'], $subject, $message, 1, 1);
            } else {
                $amid = explode(",", $modules);
                foreach ($amid as $val) {
                    if ($val != "" && $val == $mid) {
                        mail_send($email, $conf['adminmail'], $subject, $message, 1, 1);
                        break;
                    }
                }
            }
        }
    }
}

# Mail check
function checkemail($mail) {
    global $stop;
    $mail = strtolower(text_filter($mail, 1));
    if ((!$mail) || ($mail=="") || (!preg_match("#^[_\.a-z0-9-]+@([a-z0-9_-]+\.)+[a-z]{2,6}$#", $mail))) $stop[] = _ERROR1."<br>"._ERROR2." (<b>email@domain.com</b>)";
    if ((strlen($mail) >= 4) && (substr($mail, 0, 4) == "www.")) $stop[] = _ERROR1."<br>"._ERROR3." (<b>www.</b>)";
    if (strrpos($mail, " ") > 0) $stop[] = _ERROR1."<br>"._ERROR4.".";
    return $stop;
}

# Format add block
function addblocks($str) {
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
                blocks('l');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'r':
            if ($blocks == "" || $blocks == "0"|| $blocks == "2") {
                ob_start();
                blocks('r');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'c':
            if ($blocks_c == "" || $blocks_c == "0" || $blocks_c == "1") {
                ob_start();
                blocks('c');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'd':
            if ($blocks_c == "" || $blocks_c == "0"|| $blocks_c == "2") {
                ob_start();
                blocks('d');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = "";
            }
            break;
            case 'b':
            blocks('b');
            $blk[1][$i] = $showbanners;
            break;
            case 'f':
            blocks('f');
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
            blocks($telo[0], $telo[1]);
            $blk[1][$i] = ob_get_clean();
            break;
        }
    }
    return preg_replace($blk[0], $blk[1], $str);
}

# Format block
function blocks($side, $fly="") {
    global $prefix, $db, $conf, $currentlang, $name, $home, $pos, $b_id, $blockfile;
    static $barr;
    $querylang = ($conf['multilingual'] == 1) ? "AND (blanguage = '".$currentlang."' OR blanguage = '')" : "";
    $pos = strtolower($side[0]);
    $side = $pos;
    if (!isset($barr)) {
        $result = $db->sql_query("SELECT bid, bkey, title, content, url, blockfile, view, expire, action, bposition, which FROM ".$prefix."_blocks WHERE active = '1' ".$querylang." ORDER BY weight ASC");
        while(list($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $which) = $db->sql_fetchrow($result)) {
            $bid = intval($bid);
            $content = bb_decode($content, "all");
            $view = intval($view);
            $where_mas = explode(",", $which);
            $barr[] = array($bid, $bkey, $title, $content, $url, $blockfile, $view, $expire, $action, $bposition, $where_mas);
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
                    return render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 1 && is_user() || is_moder()) {
                    return render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 2 && is_moder()) {
                    return render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 3 && !is_user() || is_moder()) {
                    return render_blocks($side, $blockfile, $title, $content, $bid, $url);
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
                        $db->sql_query("UPDATE ".$prefix."_blocks SET active = '0', expire = '0' WHERE bid = '".$bid."'");
                        return;
                    } elseif ($action == "r") {
                        $db->sql_query("DELETE FROM ".$prefix."_blocks WHERE bid = '".$bid."'");
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
function render_blocks($side, $blockfile, $blocktitle, $content, $bid, $url) {
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
            return setTemplateBlock('', array('{%title%}' => $blocktitle, '{%content%}' => $content));
            break;
            default:
            echo setTemplateBlock('', array('{%title%}' => $blocktitle, '{%content%}' => $content));
            break;
        }
    } else {
        rss_load($bid);
    }
}

# Format rating
function rating() {
    global $db, $prefix, $user, $confra;
    $id = isset($_GET['id']) ? intval($_GET['id']) : "";
    $typ = isset($_GET['typ']) ? analyze($_GET['typ']) : "";
    $mod = isset($_GET['mod']) ? analyze($_GET['mod']) : "";
    $rate = (isset($_GET['rate']) && isInt($_GET['rate']) && ($_GET['rate']) <= 5) ? intval($_GET['rate']) : 0;
    $stl = isset($_GET['stl']) ? intval($_GET['stl']) : 0;
    $con = explode("|", $confra[strtolower($mod)]);
    if ($id && $mod) {
        if ($mod == "account") {
            $query = "user_votes, user_totalvotes FROM ".$prefix."_users WHERE user_id = '".$id."'";
        } elseif ($mod == "faq") {
            $query = "ratings, score FROM ".$prefix."_faq WHERE fid = '".$id."'";
        } elseif ($mod == "files") {
            $query = "votes, totalvotes FROM ".$prefix."_files WHERE lid = '".$id."'";
        } elseif ($mod == "forum") {
            $query = "ratings, score FROM ".$prefix."_forum WHERE id = '".$id."'";
        } elseif ($mod == "help") {
            $query = "ratings, score FROM ".$prefix."_help WHERE sid = '".$id."'";
        } elseif ($mod == "jokes") {
            $query = "ratingtot, rating FROM ".$prefix."_jokes WHERE jokeid = '".$id."'";
        } elseif ($mod == "links") {
            $query = "votes, totalvotes FROM ".$prefix."_links WHERE lid = '".$id."'";
        } elseif ($mod == "media") {
            $query = "votes, totalvotes FROM ".$prefix."_media WHERE id = '".$id."'";
        } elseif ($mod == "news") {
            $query = "ratings, score FROM ".$prefix."_news WHERE sid = '".$id."'";
        } elseif ($mod == "pages") {
            $query = "ratings, score FROM ".$prefix."_pages WHERE pid = '".$id."'";
        } elseif ($mod == "shop") {
            $query = "votes, totalvotes FROM ".$prefix."_products WHERE id = '".$id."'";
        }
        $ip = getIp();
        $past = time() - intval($con[0]);
        $cmod = substr($mod, 0, 2)."-".$id;
        $cookies = isset($_COOKIE[$cmod]) ? intval($_COOKIE[$cmod]) : "";
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->sql_query("DELETE FROM ".$prefix."_rating WHERE time < '".$past."' AND modul = '".$mod."'");
        list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_rating WHERE (mid = '".$id."' AND modul = '".$mod."' AND host = '".$ip."') OR (mid = '".$id."' AND modul = '".$mod."' AND uid = '".$uid."' AND uid != '0')"));
        if ($cookies == $id || $num > 0) {
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query("SELECT ".$query));
            echo ajax_rating(2, "", "", $votes, $totalvotes, "", $stl);
        } elseif (!$cookies && !$num && !$rate) {
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query("SELECT ".$query));
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
            $db->sql_query("INSERT INTO ".$prefix."_rating VALUES (NULL, '".$id."', '".$mod."', '".$new."', '".$uid."', '".$ip."')");
             if ($mod == "account" || $mod == "members") {
                $db->sql_query("UPDATE ".$prefix."_users SET user_votes=user_votes+1, user_totalvotes=user_totalvotes+".$rate." WHERE user_id = '".$id."'");
                update_points(2);
            } elseif ($mod == "faq") {
                $db->sql_query("UPDATE ".$prefix."_faq SET score=score+".$rate.", ratings=ratings+1 WHERE fid = '".$id."'");
                update_points(8);
            } elseif ($mod == "files") {
                $db->sql_query("UPDATE ".$prefix."_files SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE lid = '".$id."'");
                update_points(12);
            } elseif ($mod == "forum") {
                $db->sql_query("UPDATE ".$prefix."_forum SET score=score+".$rate.", ratings=ratings+1 WHERE id = '".$id."'");
                update_points(15);
            } elseif ($mod == "help") {
                $db->sql_query("UPDATE ".$prefix."_help SET score=score+".$rate.", ratings=ratings+1 WHERE sid = '".$id."'");
            } elseif ($mod == "gallery") {
                #$db->sql_query("UPDATE ".$prefix."_gallery SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE lid = '".$id."'");
                update_points(18);
            } elseif ($mod == "jokes") {
                $db->sql_query("UPDATE ".$prefix."_jokes SET rating=rating+".$rate.", ratingtot=ratingtot+1 WHERE jokeid = '".$id."'");
                update_points(20);
            } elseif ($mod == "links") {
                $db->sql_query("UPDATE ".$prefix."_links SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE lid = '".$id."'");
                update_points(24);
            } elseif ($mod == "media") {
                $db->sql_query("UPDATE ".$prefix."_media SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE id = '".$id."'");
                update_points(27);
            } elseif ($mod == "multimedia") {
                #$db->sql_query("UPDATE ".$prefix."_multimedia SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE id = '".$id."'");
                update_points(30);
            } elseif ($mod == "news") {
                $db->sql_query("UPDATE ".$prefix."_news SET score=score+".$rate.", ratings=ratings+1 WHERE sid = '".$id."'");
                update_points(33);
            } elseif ($mod == "pages") {
                $db->sql_query("UPDATE ".$prefix."_pages SET score=score+".$rate.", ratings=ratings+1 WHERE pid = '".$id."'");
                update_points(37);
            } elseif ($mod == "shop") {
                $db->sql_query("UPDATE ".$prefix."_products SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE id = '".$id."'");
                update_points(41);
            }
            list($votes, $totalvotes) = $db->sql_fetchrow($db->sql_query("SELECT ".$query));
            echo ajax_rating(2, "", "", $votes, $totalvotes, "", $stl);
        }
    }
}

# Format BB Code and Smilies
function textarea() {
    global $admin, $op, $user, $conf;
    $arg = func_get_args();
    $id = $arg[0];
    $name = $arg[1];
    $var = $arg[2];
    $mod = $arg[3];
    $rows = $arg[4];
    $placeholder = (!empty($arg[5])) ? " placeholder=\"".$arg[5]."\"" : "";
    $required = (!empty($arg[6])) ? " required" : "";
    $stloc = substr(_LOCALE, 0, 2);
    $desc = ($var) ? $var : (isset($_POST[$name]) ? save_text($_POST[$name]) : "");
    include("config/config_uploads.php");
    $con = explode("|", $confup[strtolower($mod)]);
    $style = (defined('ADMIN_FILE')) ? ' sl_form' : ' '.$conf['style'];
    $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 1;
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
                <td>Х</td><td>Ц</td><td>Ч</td><td>Ш</td><td>Щ</td><td>Ь</td><td>Ы</td><td>Ъ</td><td>Э</td><td>Ю</td><td>Я</td>
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
        $font = array("Arial", "Courier", "Mistral", "Impact", "Sans Serif", "Tahoma", "Helvetica", "Verdana");
        foreach ($font as $val) if ($val != "") $fonts .= "<option style=\"font-family: ".$val.";\" value=\"".$val."\">".$val."</option>";
        
        $colors = "<option value=\"\">"._ECOLOR."</option>";
        $color = array("black", "gray", "silver", "white", "maroon", "red", "orangered", "orange", "yellow", "purple", "fuchsia", "violet", "darkgreen", "green", "lime", "navy", "blue", "teal", "aqua");
        foreach ($color as $val) if ($val != "") $colors .= "<option style=\"background: ".$val.";\" value=\"".$val."\">".$val."</option>";
        
        $fsizes = "<option value=\"\">"._ESIZE."</option>";
        $fsize = array("8", "10", "12", "14", "16", "18", "20", "22", "24", "26", "28", "30", "32");
        foreach ($fsize as $val) if ($val != "") $fsizes .= "<option value=\"".$val."\">".$val."</option>";
        
        $fcodes = "<option value=\"\">"._CODE."</option>";
        $fcode = array("Bash", "Cpp", "CSharp", "Css", "Delphi", "Diff", "Groovy", "Java", "JScript", "Php", "Plain", "Python", "Ruby", "Scala", "Sql", "Vb", "Xml");
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
function textareae($obj, $go, $op, $id, $cid, $typ, $mod, $text, $rows) {
    global $conf, $admin;
    $editor = intval(substr($admin[3], 0, 1));
    $desc = ((defined("ADMIN_FILE") && $editor == 1) || (!defined("ADMIN_FILE") && $conf['redaktor'] == 1)) ? replace_break($text) : $text;
    $code = "<form name=\"textareae\" id=\"form".$obj."\" method=\"post\">
    <textarea id=\"text\" name=\"text\" cols=\"65\" rows=\"".$rows."\" class=\"sl_earea\">".$desc."</textarea>
    <input type=\"submit\" OnClick=\"AjaxLoad('POST', '1', '".$obj."', 'go=".$go."&amp;op=".$op."&amp;id=".$id."&amp;cid=".$cid."&amp;typ=".$typ."&amp;mod=".$mod."', { 'text':'"._CERROR1."' }); return false;\" value=\""._SAVE."\" title=\""._SAVE."\" class=\"sl_but_green\">
    <input type=\"submit\" OnClick=\"AjaxLoad('GET', '1', '".$obj."', 'go=".$go."&amp;op=".$op."&amp;id=".$id."&amp;cid=".$cid."&amp;typ=".$typ."&amp;mod=".$mod."', ''); return false;\" value=\""._BACK."\" title=\""._BACK."\" class=\"sl_but_blue\">
    </form>";
    return $code;
}

# Format code edit
function textarea_code($id, $name, $style, $mode, $text) {
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
function num_ajax() {
    global $admin_file;
    $arg = func_get_args();
    $mnum = ($arg[4]) ? $arg[4] : 8;
    $num = ($arg[5]) ? $arg[5] : 1;
    $ld = ($arg[6]) ? $arg[6] : "";
    $go = ($arg[7]) ? $arg[7] : 0;
    $op = ($arg[8]) ? $arg[8] : "";
    $id = ($arg[9]) ? $arg[9] : 0;
    $cid = ($arg[10]) ? $arg[10] : 0;
    $typ = ($arg[11]) ? $arg[11] : "";
    $mod = ($arg[12]) ? $arg[12] : "";
    $nnum = $mnum + 1;
    if ($arg[2] > 1) {
        $cont = "";
        if ($num > 1) {
            $prev = $num - 1;
            $cprev = "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$prev."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" class=\"sl_num\" title=\""._BACK."\">"._BACK."</a>";
        } else {
            $cprev = "<span class=\"sl_num\" title=\""._BACK."\">"._BACK."</span>";
        }
        for ($i = 1; $i < $arg[2]+1; $i++) {
            if ($i == $num) {
                $cont .= "<span title=\"".$i."\">".$i."</span>";
            } else {
                if ((($i > ($num - $mnum)) && ($i < ($num + $mnum))) || ($i == $arg[2]) || ($i == 1)) $cont .= "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$i."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" title=\"".$i."\">".$i."</a>";
            }
            if ($i < $arg[2]) {
                if (($i > ($num - $nnum)) && ($i < ($num + $mnum))) $cont .= " ";
                if (($num > $nnum) && ($i == 1)) $cont .= "<span class=\"sl_num_exit\" title=\"&hellip;\">&hellip;</span>";
                if (($num < ($arg[2] - $mnum)) && ($i == ($arg[2] - 1))) $cont .= "<span class=\"sl_num_exit\" title=\"&hellip;\">&hellip;</span>";
            }
        }
        if ($num < $arg[2]) {
            $next = $num + 1;
            $cnext = " <a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go."&amp;op=".$op."&amp;id=".$cid."&amp;cid=".$next."&amp;typ=".$typ."&amp;dir=".$mod."', ''); return false;\" class=\"sl_num\" title=\""._NEXT."\">"._NEXT."</a>";
        } else {
            $cnext = "<span class=\"sl_num\" title=\""._NEXT."\">"._NEXT."</span>";
        }
        return tpl_eval($arg[0], _OVERALL, $arg[1], _BY, $arg[2], _PAGE_S, $arg[3], _PERPAGE, $cont, $cprev, $cnext);
    }
}

# Check type upload file
function check_file($type, $typefile) {
    $strtypefile = str_replace(",", "|", $typefile);
    if (!preg_match("#".$strtypefile."#i", $type) || preg_match("#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i", $type)) return _ERROR_FILE;
}

# Check size upload file
function check_size($file, $width, $height) {
    list($imgwidth, $imgheight) = getimagesize($file);
    if ($imgwidth > $width || $imgheight > $height) return _ERROR_SIZE;
}

# Crypted md5 and salt
function md5_salt($pass) {
    global $conf;
    $crypt = md5(md5($conf['lic_f']).md5($pass));
    return $crypt;
}

# Upload file
function upload($typ, $directory, $typefile, $maxsize, $namefile, $width, $height, $userid='', $url='') {
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
        if (isset($_FILES['file']) && !empty($_FILES['file']) && $_POST['token'] == md5_salt($conf['sitekey'])) {
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
    } elseif ($typ == 3 && !empty($_POST['sitefile'])) {
        $afile = str_replace(array('&', '?', '#'), '', $_POST['sitefile']);
        $type = strtolower(substr(strrchr($afile, '.'), 1));
        if (!check_file($type, $typefile) && !check_size($_POST['sitefile'], $width, $height)) {
            $fn = $_POST['sitefile'];
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
            $stop = (!check_file($type, $typefile)) ? check_size($_POST['sitefile'], $width, $height) : check_file($type, $typefile);
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
function language($lang='', $typ='') {
    $dir = opendir("language");
    $cont = (!$typ) ? "<option value=\"\">"._ALL."</option>" : "";
    while (false !== ($file = readdir($dir))) {
        if (preg_match("#^lang\-(.+)\.php#", $file, $matches)) {
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
function modul($name, $class, $modul, $no='') {
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
function cat_modul() {
    $arg = func_get_args();
    $submit = isset($arg[3]) ? " OnChange=\"submit()\"" : "";
    $class = isset($arg[1]) ? " class=\"".$arg[1]."\"" : "";
    $content = "<select name=\"".$arg[0]."\"".$class.$submit.">";
    $mods = array("faq", "files", "forum", "help", "jokes", "links", "media", "news", "pages", "shop");
    for ($i = 0; $i < count($mods); $i++) {
        $sel = ($arg[2] == $mods[$i]) ? " selected" : "";
        $content .= "<option value=\"".$mods[$i]."\"".$sel.">".deflmconst($mods[$i])." - ".$mods[$i]."</option>";
    }
    $content .= "</select>";
    return $content;
}

# Format editor
function redaktor($id, $name, $class, $editor, $submit) {
    global $conf;
    $submit = ($submit) ? ' OnChange="submit()"' : '';
    $class = ($class) ? ' class="'.$class.'"' : '';
    $content = '<select name="'.$name.'"'.$submit.$class.'>';
    $ename = ($id == 1) ? array(0 => _NO, 1 => _EDITOR.' SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => _EDITOR.' TinyMCE 4.5.6', 3 => _EDITOR.' CKEditor 4.6.2', 4 => _EDITOR.' CodeMirror 5.25.0') : array(0 => _NO, 1 => _EDITOR.' SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => _EDITOR.' TinyMCE 4.5.6', 3 => _EDITOR.' CKEditor 4.6.2');
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
function ashowcom() {
    global $prefix, $db, $admin_file, $conf, $confu, $confc, $confpr, $user, $currentlang;
    $arg = func_get_args();
    $cid = isset($arg[0]) ? intval($arg[0]) : "";
    $mod = isset($arg[1]) ? analyze($arg[1]) : "";
    if (defined("ADMIN_FILE")) {
        $ordern = (isset($_GET['status']) == 1) ? "WHERE status = '0'" : "WHERE status != '0'";
        $ccnum = $confc['anum'];
        $plnum = $confc['anump'];
    } else {
        $ordern = (is_moder($mod)) ? "WHERE cid = '".$cid."' AND modul = '".$mod."'" : "WHERE cid = '".$cid."' AND modul = '".$mod."' AND status != '0'";
        $ccnum = $confc['num'];
        $plnum = $confc['nump'];
    }
    list($numstories) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(cid) FROM ".$prefix."_comment ".$ordern));
    if ($numstories > 0) {
        $com = getVar('get', 'com', 'num', '1');
        $offset = ($com - 1) * $ccnum;
        $numpages = ceil($numstories / $ccnum);
        if ($confc['sort']) {
            $sort = "ASC";
            $a = ($com) ? $offset+1 : 1;
        } else {
            $sort = "DESC";
            $a = $numstories;
            if ($numstories > $offset) $a -= $offset;
        }
        $where = array();
        $result = $db->sql_query("SELECT id, cid, modul, date, uid, name, host_name, comment, status FROM ".$prefix."_comment ".$ordern." ORDER BY date ".$sort." LIMIT ".$offset.", ".$ccnum);
        while (list($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status) = $db->sql_fetchrow($result)) {
            $cmassiv[] = array($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status);
            if ($com_uid) $where[] = $com_uid;
            unset($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status);
        }
        if ($where) {
            $result2 = $db->sql_query("SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_from, u.user_sig, u.user_viewemail, u.user_points, u.user_warnings, u.user_gender, u.user_votes, u.user_totalvotes, g.name, g.rank, g.color FROM ".$prefix."_users AS u LEFT JOIN ".$prefix."_groups AS g ON ((g.extra = 1 AND u.user_group = g.id) OR (g.extra != 1 AND u.user_points >= g.points)) WHERE u.user_id IN (".implode(", ", $where).") ORDER BY g.extra ASC, g.points ASC");
            while (list($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor) = $db->sql_fetchrow($result2)) {
                $umassiv[] = array($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
                unset($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
            }
        }
        $cont = "";
        if (defined("ADMIN_FILE")) {
            $cont .= "<form name=\"comm\" action=\"".$admin_file.".php\" method=\"post\">";
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
            $avname = (!empty($user_name)) ? $user_name : $com_name." (".$confu['anonym'].")";
            $date = "<span title=\""._PADD."\" class=\"sl_t_post\">".format_time($com_date, _TIMESTRING)."</span>";
            $ip = (is_moder($com_modul)) ? user_geo_ip($com_host, 4) : "";
            $amess = "<a href=\"#".$com_id."\" title=\""._COMMENT.": ".$a."\" class=\"sl_pnum\">".$a."</a>";
            $avatar = (!empty($user_name)) ? (($user_avatar && file_exists($confu['adirectory']."/".$user_avatar)) ? $confu['adirectory']."/".$user_avatar : $confu['adirectory']."/default/00.gif") : $confu['adirectory']."/default/0.gif";
            $rank = (!empty($user_rank)) ? $user_rank : "";
            $trank = (!empty($user_gname)) ? _GROUP.": ".$user_gname : _RANK;
            $rlink = (!empty($user_grank) && file_exists(img_find("ranks/".$user_grank))) ? "<img src=\"".img_find("ranks/".$user_grank)."\" alt=\"".$trank."\" title=\"".$trank."\">" : "";
            $rate = (!empty($user_id)) ? ajax_rating(0, $user_id, "account", $user_votes, $user_totalvotes, $com_id, 1) : "";
            $rwarn = (!empty($user_warnings)) ? _UWARNS.": ".warnings($user_warnings) : "";
            $group = (!empty($user_gname)) ? _GROUP.": <span style=\"color: ".$user_gcolor."\">".$user_gname."</span>" : "";
            $point = ($confu['point'] && !empty($user_points)) ? _POINTS.": ".$user_points : "";
            $regdate = (!empty($user_regdate)) ? _REG.": ".format_time($user_regdate) : _NO_INFO;
            $gender = (!empty($user_gender)) ? _GENDER.": ".gender($user_gender) : "";
            $from = (!empty($user_from)) ? _FROM.": ".$user_from : "";
            $sig = (!empty($user_sig)) ? "<hr>".$user_sig : "";
            $personal = (is_moder($com_modul) || is_user() || $confc['anonpost'] != 0) ? "<a href=\"javascript: InsertCode('name', '".$avname."', '', '', '1');\" title=\""._PERSONAL."\" class=\"sl_but_blue\">"._PERS."</a>" : "";
            $privat = ($confc['privat'] && $confpr['act'] && !empty($user_name)) ? "<a href=\"index.php?name=account&amp;op=privat&amp;uname=".urlencode($user_name)."\" title=\""._SENDMES."\" class=\"sl_but_green\">"._MESSAGE."</a>" : "";
            $profil = ($confc['profil'] && !empty($user_name)) ? "<a href=\"index.php?name=account&amp;op=view&amp;uname=".urlencode($user_name)."\" title=\""._PERSONALINFO."\" class=\"sl_but\">"._ACCOUNT."</a>" : "";
            $web = ($confc['web'] && !empty($user_website)) ? "<a href=\"".$user_website."\" target=\"_blank\" title=\""._DOWNLLINK."\" class=\"sl_but\">"._SITE."</a>" : "";
            
            # Будущие функции
            #$warn = "<a href=\"javascript: scroll(0, 0);\" title=\""._WARNM."\">"._WARNM."</a>";
            #$thank = "<a href=\"javascript: scroll(0, 0);\" title=\""._THANK."\">"._THANK."</a>";
            $warn = "";
            $thank = "";
            
            if (is_moder($com_modul)) {
                if (defined("ADMIN_FILE")) {
                    $edit = add_menu("<a href=\"index.php?name=".$com_modul."&amp;op=view&amp;id=".$com_cid."#".$com_id."\" title=\""._MVIEW."\">"._MVIEW."</a>||<a href=\"".$admin_file.".php?op=comm_edit&amp;id=".$com_id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=comm_act&amp;id=".$com_id."&amp;refer=1\" title=\""._ACTIVATE."\">"._ACTIVATE."</a>||<a href=\"".$admin_file.".php?op=comm_del&amp;id=".$com_id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".cutstr(text_filter(bb_decode($com_text, $com_modul)), 10)."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>");
                } else {
                    $edit = add_menu("<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=editcom&amp;id=".$com_id."&amp;typ=1&amp;mod=".$com_modul."', ''); return false;\" title=\""._ONEDIT."\">"._ONEDIT."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id."&amp;typ=0&amp;mod=".$com_modul."', ''); return false;\" title=\""._FMODC."\">"._FMODC."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id."&amp;typ=1&amp;mod=".$com_modul."', ''); return false;\" title=\""._ACTIVATE."\">"._ACTIVATE."</a>");
                }
            } else {
                $stime = strtotime($com_date) + $confc['edit'];
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
            $cont .= tpl_func("comment", $com_id, $avname, $date, $ip, $amess, $avatar, $rank, $rlink, $rate, $rwarn, $group, $point, $regdate, $gender, $from, $text, bb_decode($sig, $com_modul), $personal, $privat, $profil, $web, $warn, $thank, $edit, $hclass, $checkb);
            if ($confc['sort']) { $a++; } else { $a--; }
        }
        if (defined("ADMIN_FILE")) {
            $selms = _CHECKOP.": <select name=\"op\"><option value=\"comm_act\">"._ACTIVATE."</option><option value=\"comm_del\">"._DELETE."</option></select> <input type=\"hidden\" name=\"refer\" value=\"1\"><input type=\"submit\" value=\""._OK."\" class=\"sl_but_blue\">";
            $pag = (isset($_GET['status']) == 1) ? "op=comm_show&amp;status=1" : "op=comm_show";
            $numpt = setPageNumbers("pagenum", $com_modul, $numstories, $numpages, $ccnum, $pag."&amp;", $plnum, '', 'com');
            $cont .= tpl_eval("list-bottom", $numpt, $selms);
            $out = tpl_func("open").$cont.tpl_func("close", "</form>");
        } else {
            $num = getVar('get', 'num', 'num');
            $pag = empty($num) ? 'op=view&id='.$cid : 'op=view&id='.$cid.'&num='.$num;
            $cont .= setPageNumbers('pagenum', $com_modul, $numstories, $numpages, $ccnum, $pag.'&', $plnum, '', '#comm', 'com');
            $out = setTemplateBasic('title', array('{%title%}' => _COMMENTS)).setTemplateBasic('open').$cont.setTemplateBasic('close');
        }
    } else {
        $winfo = (defined('ADMIN_FILE')) ? _NO_INFO : _NOCOMMENTS;
        $out = setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => $winfo));
    }
    return $out;
}

# Save edit comments
function editcom() {
    global $prefix, $db, $user, $confc;
    $id = (isset($_POST['id'])) ? ((isset($_POST['id'])) ? intval($_POST['id']) : "") : ((isset($_GET['id'])) ? intval($_GET['id']) : "");
    $typ = (isset($_POST['typ'])) ? ((isset($_POST['typ'])) ? intval($_POST['typ']) : "") : ((isset($_GET['typ'])) ? intval($_GET['typ']) : "");
    $mod = (isset($_POST['mod'])) ? ((isset($_POST['mod'])) ? analyze($_POST['mod']) : "") : ((isset($_GET['mod'])) ? analyze($_GET['mod']) : "");
    $text = (isset($_POST['text'])) ? ((isset($_POST['text'])) ? trim($_POST['text']) : "") : ((isset($_GET['text'])) ? trim($_GET['text']) : "");
    list($uid, $date, $comment) = $db->sql_fetchrow($db->sql_query("SELECT uid, date, comment FROM ".$prefix."_comment WHERE id = '".$id."'"));
    $stime = strtotime($date) + $confc['edit'];
    if (is_moder($mod) || (is_user() && $uid == intval($user[0]) && time() < $stime)) {
        if ($id && $mod && !$text) {
            $content = ($typ) ? textareae("com".$id, "1", "editcom", $id, "0", "0", $mod, $comment, "10") : bb_decode($comment, $mod);
            echo $content;
        } elseif ($id && $mod && $text) {
            $checks = str_replace(array("\n", "\r", "\t"), " ", $text);
            $e = explode(" ", $checks);
            for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
            $stop = array();
            if ($text == "") $stop[] = _CERROR1;
            if ($o > $confc['letter']) $stop[] = _CERROR2;
            if (!is_moder($mod) && (($confc['link'] == 1 && !is_user()) || ($confc['link'] == 2)) && stripos($text, "http://") !== false) $stop[] = _CERROR9;
            $urlclick = (!is_moder($mod) && (($confc['alink'] == 1 && !is_user()) || ($confc['alink'] == 2))) ? 1 : 0;
            if (!$stop) {
                $comm = save_text($text, $urlclick);
                $db->sql_query("UPDATE ".$prefix."_comment SET comment = '".$comm."' WHERE id = '".$id."'");
                echo bb_decode($comm, $mod);
            } else {
                return tpl_warn("warn", $stop, "", "", "warn");
            }
        }
    } else {
        $info = sprintf(_PEDEND, intval($confc['edit'] / 60));
        return tpl_warn("warn", $info, "", "", "warn");
    }
}

# Close comments
function closecom() {
    global $prefix, $db;
    $id = (isset($_POST['id'])) ? ((isset($_POST['id'])) ? intval($_POST['id']) : "") : ((isset($_GET['id'])) ? intval($_GET['id']) : "");
    $typ = (isset($_POST['typ'])) ? ((isset($_POST['typ'])) ? intval($_POST['typ']) : 0) : ((isset($_GET['typ'])) ? intval($_GET['typ']) : 0);
    $mod = (isset($_POST['mod'])) ? ((isset($_POST['mod'])) ? analyze($_POST['mod']) : "") : ((isset($_GET['mod'])) ? analyze($_GET['mod']) : "");
    if ($id && $mod && is_moder($mod)) {
        $status = ($typ) ? 1 : 0;
        $info = ($typ) ? _PCOPEN : _PCLOSED;
        $numcom = ($typ) ? 0 : 1;
        $db->sql_query("UPDATE ".$prefix."_comment SET status = '".$status."' WHERE id = '".$id."'");
        list($cid, $uid) = $db->sql_fetchrow($db->sql_query("SELECT cid, uid FROM ".$prefix."_comment WHERE id = '".$id."'"));
        numcom($cid, $mod, $numcom, $uid);
        echo tpl_warn("warn", $info, "", "", "warn");
    }
}

# Number comments
function numcom() {
    global $prefix, $db;
    $arg = func_get_args();
    $id = ($arg[0]) ? intval($arg[0]) : 0;
    $mod = ($arg[1]) ? analyze($arg[1]) : "";
    $typ = ($arg[2]) ? "-1" : "+1";
    $uid = ($arg[3]) ? intval($arg[3]) : 0;
    $point = ($arg[2]) ? 1 : 0;
    if ($id && $mod) {
        if ($mod == "account" || $mod == "members") {
            #$db->sql_query("UPDATE ".$prefix."_users SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(3, $uid, $point);
        } elseif ($mod == "faq") {
            $db->sql_query("UPDATE ".$prefix."_faq SET comments=comments".$typ." WHERE fid = '".$id."'");
            update_points(7, $uid, $point);
        } elseif ($mod == "files") {
            $db->sql_query("UPDATE ".$prefix."_files SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(10, $uid, $point);
        } elseif ($mod == "gallery") {
            #$db->sql_query("UPDATE ".$prefix."_gallery SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(17, $uid, $point);
        } elseif ($mod == "links") {
            $db->sql_query("UPDATE ".$prefix."_links SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(22, $uid, $point);
        } elseif ($mod == "media") {
            $db->sql_query("UPDATE ".$prefix."_media SET totalcom=totalcom".$typ." WHERE id = '".$id."'");
            update_points(26, $uid, $point);
        } elseif ($mod == "multimedia") {
            #$db->sql_query("UPDATE ".$prefix."_multimedia SET totalcom=totalcom".$typ." WHERE id = '".$id."'");
            update_points(29, $uid, $point);
        } elseif ($mod == "news") {
            $db->sql_query("UPDATE ".$prefix."_news SET comments=comments".$typ." WHERE sid = '".$id."'");
            update_points(32, $uid, $point);
        } elseif ($mod == "pages") {
            $db->sql_query("UPDATE ".$prefix."_pages SET comments=comments".$typ." WHERE pid = '".$id."'");
            update_points(36, $uid, $point);
        } elseif ($mod == "shop") {
            $db->sql_query("UPDATE ".$prefix."_products SET com=com".$typ." WHERE id = '".$id."'");
            update_points(40, $uid, $point);
        } elseif ($mod == "voting") {
            $db->sql_query("UPDATE ".$prefix."_voting SET comments=comments".$typ." WHERE id = '".$id."'");
            update_points(43, $uid, $point);
        }
    }
}

# Voting result save
function avoting_save() {
    global $db, $prefix, $user, $currentlang, $conf, $confv;
    $id = intval($_POST['id']);
    $questions = $_POST['questions'];
    $querylang = ($conf['multilingual'] == 1) ? "(language = '".$currentlang."' OR language = '') AND date <= NOW() AND enddate >= NOW()" : "date <= NOW() AND enddate >= NOW()";
    $result = $db->sql_query("SELECT id FROM ".$prefix."_voting WHERE id = '".$id."' AND ".$querylang);
    if ($db->sql_numrows($result) > 0) {
        if (!$questions) {
            $cont = tpl_warn("warn", _SEROR1, "?name=voting&amp;op=view&amp;id=".$id, 3, "warn");
        } else {
            $ip = getIp();
            $past = time() - intval($confv['voting_t']);
            $cmod = substr("voting", 0, 2)."-".$id;
            $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : "";
            $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
            $db->sql_query("DELETE FROM ".$prefix."_rating WHERE time < '".$past."' AND modul = 'voting'");
            list($num) = $db->sql_fetchrow($db->sql_query("SELECT COUNT(id) FROM ".$prefix."_rating WHERE (mid = '".$id."' AND modul = 'voting' AND host = '".$ip."') OR (mid = '".$id."' AND modul = 'voting' AND uid = '".$uid."' AND uid != '0')"));
            if ($cookies == $id || $num > 0) {
                $cont = tpl_warn("warn", _SEROR2, "?name=voting&amp;op=view&amp;id=".$id, 3, "warn");
            } else {
                setcookie(substr("voting", 0, 2)."-".$id, $id, time() + intval($confv['voting_t']));
                $new = time();
                $db->sql_query("INSERT INTO ".$prefix."_rating VALUES (NULL, '".$id."', 'voting', '".$new."', '".$uid."', '".$ip."')");
                list($answer) = $db->sql_fetchrow($db->sql_query("SELECT answer FROM ".$prefix."_voting WHERE id = '".$id."'"));
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
                $db->sql_query("UPDATE ".$prefix."_voting SET answer = '".$answ."' WHERE id = '".$id."'");
                update_points(42);
                $cont = getVoting($id);
            }
        }
    } else {
        $cont = setTemplateWarning('warn', array('time' => '3', 'url' => '?name=voting', 'id' => 'warn', 'text' => _ERROR));
    }
    echo $cont;
}

# Update points
function update_points() {
    global $prefix, $db, $user, $conf, $confu;
    $arg = func_get_args();
    $id = intval($arg[0]);
    $uid = (!empty($arg[1])) ? intval($arg[1]) : ((is_user()) ? intval($user[0]) : "");
    if ($id && $uid && $confu['point'] == 1) {
        $upoints = explode(",", $confu['points']);
        $a = $id - 1;
        $rpoints = (!empty($arg[2])) ? "-".$upoints[$a] : "+".$upoints[$a];
        $db->sql_query("UPDATE ".$prefix."_users SET user_points = user_points".$rpoints." WHERE user_id = '".$uid."'");
    }
}

# Format statistic image
function create_stat() {
    global $conf;
    include("config/config_stat.php");
    $arg = func_get_args();
    $report = ($arg[0]) ? intval($arg[0]) : ((isset($_GET['report'])) ? intval($_GET['report']) : 0);
    $mday = ($arg[1]) ? intval($arg[1]) : ((isset($_GET['day'])) ? intval($_GET['day']) : "15");
    $file = ($arg[2]) ? text_filter($arg[2]) : ((isset($_GET['file'])) ? text_filter($_GET['file']) : "");
    $off = 1;
    
    if (!$report) header("Content-type: image/png");
    $image = imagecreate(800, 340);
    
    $white = imagecolorallocate($image, 255, 255, 255);
    $red = imagecolorallocate($image, 255, 0, 0);
    $green = imagecolorallocate($image, 0, 128, 0);
    $purple = imagecolorallocate($image, 200, 0, 200);
    $black = imagecolorallocate($image, 0, 0, 0);
    $wblue = imagecolorallocate($image, 34, 122, 199);
    $wgreen = imagecolorallocate($image, 44, 135, 16);
    $gray = imagecolorallocate($image, 203, 218, 226);
    $yellow = imagecolorallocate($image, 207, 179, 31);
    $llgray = imagecolorallocate($image, 250, 250, 250);

    imagefilledrectangle($image, 0, 252, 800, 340, $llgray);

    $f = array();
    if ($report) {
        $f = (file_exists("config/counter/days.txt")) ? file("config/counter/days.txt") : file("config/counter/stat.txt");
    } else {
        if ($file) {
            $f = file("config/counter/stat/".$file);
        } else {
            if (file_exists("config/counter/days.txt")) {
                $f = file("config/counter/days.txt");
                $f = array_merge($f, file("config/counter/stat.txt"));
            } else {
                $f = file("config/counter/stat.txt");
            }
        }
    }
    $to = count($f);
    if ($mday > 15) {
        $from = 0;
        $to = 15;
    } else {
        $from = (!$file && date("d") <= 15) ? 0 : 15;
        if ($from < 0) $from = 0;
    }
    $unique = $today = $engines = $sites = $homepage = $auditory = $max1 = $max2 = 0;
    for($i = $from; $i < $to; $i++) {
        $day = explode("|", $f[$i]);
        if ($day[1] > $max1) $max1 = $day[1];
        if ($day[2] > $max2) $max2 = $day[2];
        $unique = $unique + $day[1];
        $today = $today + $day[2];
        $engines = $engines + $day[4];
        $sites = $sites + $day[5];
        $homepage = $homepage + $day[6];
        $auditory = $auditory + $day[1] - ($day[4] + $day[5]);
        if ($auditory < 0) $auditory = 0;
        $regusers = $regusers + $day[7];
    }
    $i = 0;
    for($z = $from; $z < $to; $z++) {
        $day = explode("|", $f[$z]);
        if ($day[2] != "") {
            $w = round((230 / $max2) * $day[2]);
            if ($w < 4) $w = 4;
            $off = 134;
            imagefilledrectangle($image, $off+$confst['bet']*$i+1, 250-$w+1, $off+$confst['bet']*$i+$confst['shi'], 249, $yellow);
            imagerectangle($image, $off+$confst['bet']*$i, 250-$w, $off+$confst['bet']*$i+$confst['shi'], 249, $black);
            imagerectangle($image, $off+$confst['bet']*$i+$confst['shi']+1, 250-$w+3, $off+$confst['bet']*$i+$confst['shi']+2, 249, $gray);
            $w = round((230 / $max1) * $day[1]);
            if ($w < 5) $w = 1;
            $off = 120;
            
            imagefilledrectangle($image, $off+$confst['bet']*$i+1, 250-$w+1, $off+$confst['bet']*$i+$confst['shi']+3, 249, $wblue);
            imagerectangle($image, $off+$confst['bet']*$i,250-$w, $off+$confst['bet']*$i+$confst['shi']+3, 249, $black);
            imagerectangle($image, $off+$confst['bet']*$i+$confst['shi']+4, 250-$w+4, $off+$confst['bet']*$i+$confst['shi']+5, 249, $black);
            $zzz = $day[1] - ($day[4] + $day[5]);
            $w = round((230 / $max1) * $zzz);
            if ($w < 4) $w = $w + 31;
        
            imagefilledrectangle($image, $off+$confst['bet']*$i+1, 250-$w+1, $off+$confst['bet']*$i+$confst['shi']+3, 249, $wgreen);
            imagerectangle($image, $off+$confst['bet']*$i, 250-$w, $off+$confst['bet']*$i+$confst['shi']+3, 249, $black);
            imagestring($image, 1, $off+$confst['bet']*$i+2, 250-$w+1-10, $day[1], $white);
            
            $d = explode(".", $day[0]);
            $d = $d[0].".".$d[1];
            
            imagestring($image, 1, $off+$confst['bet']*$i+1, 255, $d, $wblue);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 265, $day[1], $red);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 275, $day[2], $green);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 285, $day[6], $purple);
            
            imagestring($image, 1, $off+$confst['bet']*$i+1, 300, $day[5], $wblue);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 310, $day[4], $red);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 320, $zzz, $green);
            imagestring($image, 1, $off+$confst['bet']*$i+1, 330, rtrim($day[7]), $purple);
            
            imagestring($image, 1, 3, 255, "DATE:", $wblue);
            imagestring($image, 1, 3, 265, "UNIQUE VISITORS:", $red);
            imagestring($image, 1, 3, 275, "SITE HITS:", $green);
            imagestring($image, 1, 3, 285, "HOMEPAGE HITS:", $purple);
            
            imagestring($image, 1, 3, 300, "OTHER SITES:", $wblue);
            imagestring($image, 1, 3, 310, "SEARCH ENGINES:", $red);
            imagestring($image, 1, 3, 320, "AUDIENCE:", $green);
            imagestring($image, 1, 3, 330, "REGISTERED USERS:", $purple);
        }
        $i++;
    }

    imagefilledrectangle($image, 5, 170, 20, 180, $wblue);
    imagerectangle($image, 5, 170, 20, 180, $black);
    imagestring($image, 1, 25, 171, "UNIQUE VISITORS", $black);
    
    imagefilledrectangle($image, 5, 185, 20, 195, $wgreen);
    imagerectangle($image, 5, 185, 20, 195, $black);
    imagestring($image, 1, 25, 186, "SITE AUDIENCE", $black);
    
    imagefilledrectangle($image, 5, 200, 20, 210, $yellow);
    imagerectangle($image, 5, 200, 20, 210, $black);
    imagestring($image, 1, 25, 202, "SITE HITS", $black);
    
    imagerectangle($image, 0, 296, 799, 339, $gray);
    imagerectangle($image, 0, 252, 800, 252, $gray);
    imagerectangle($image, 0, 0, 799, 339, $gray);
    
    imagestring($image, 1, 5, 5, "VISITS BY DAYS FOR ".strtoupper($conf['homeurl'])." BY SLAED CMS ".$conf['version']." - ".date(_TIMESTRING), $wblue);
    
    imagestring($image, 1, 5, 30, "UNIQUES TOTAL: ".$unique, $red);
    imagestring($image, 1, 5, 40, "HITS TOTAL: ".$today, $green);
    imagestring($image, 1, 5, 50, "HOMEPAGE HITS: ".$homepage, $purple);
    
    imagestring($image, 1, 5, 70, "OTHER SITES: ".$sites, $wblue);
    imagestring($image, 1, 5, 80, "SEARCH ENGINES: ".$engines, $red);
    imagestring($image, 1, 5, 90, "AUDIENCE: ".$auditory, $green);
    imagestring($image, 1, 5, 100, "REG. USERS: ".$regusers, $purple);

    imagestring($image, 1, 5, 120, "PAGES PER VIS.: ".round($today/$unique, 2), $wblue);
    imagestring($image, 1, 5, 130, "AVR. AUDIENCE: ".round($auditory/$i), $wblue);
    
    if ($report) {
        imagepng($image, "config/counter/stat/".date("m-Y").".png");
    } else {
        imagepng($image);
    }
    imagedestroy($image);
}

# Format image preview PHP GD
function create_img_gd($imgfile, $imgthumb, $newwidth) {
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
        $to = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "a", "a", "a", "a", "a", "a", "?", "c", "e", "e", "e", "e", "i", "i", "i", "i", "?", "n", "o", "o", "o", "o", "o", "o", "u", "u", "u", "u", "y", "а", "б", "в", "г", "д", "е", "ё", "ж", "з", "и", "й", "к", "л", "м", "н", "о", "п", "р", "с", "т", "у", "ф", "х", "ц", "ч", "ш", "щ", "ъ", "ы", "ь", "э", "ю", "я");
        $from = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "A", "A", "A", "A", "A", "A", "?", "C", "E", "E", "E", "E", "I", "I", "I", "I", "?", "N", "O", "O", "O", "O", "O", "O", "U", "U", "U", "U", "Y", "А", "Б", "В", "Г", "Д", "Е", "Ё", "Ж", "З", "И", "Й", "К", "Л", "М", "Н", "О", "П", "Р", "С", "Т", "У", "Ф", "Х", "Ц", "Ч", "Ш", "Щ", "Ъ", "Ъ", "Ь", "Э", "Ю", "Я");
        return str_replace($from, $to, $str);
    }
}

# Format function fputcsv for PHP 4
if (!function_exists("fputcsv")) {
    function fputcsv(&$handle, $fields = array(), $delimiter = ',', $enclosure = '"') {
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