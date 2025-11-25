<?php
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
$time_start = microtime(true);
echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"></head><body>';
echo '<pre><h2>Нахождение и сжатие CSS файлов</h2><h3>CSS файлы</h3><table>';

# Definition and processing of header scripts files
function setScript() {
	$content = '';
	$asize = $an = $f = 0;
	$theme = 'script';
	
	global $conf, $confs;
	
	$conf['cache_script'] = "1";
	$conf['script_f'] = "plugins/system/global-func.js,plugins/jquery/jquery.js,plugins/jquery/ui/jquery-ui.js,plugins/jquery/jquery.tablesorter.js,plugins/jquery/jquery.cookie.js,plugins/fancybox/jquery.mousewheel.js,plugins/fancybox/jquery.fancybox.js,plugins/jquery/jquery.slaed.js";
	$conf['script_h'] = "1";
	$conf['script_c'] = "1";
	$conf['script_a'] = "1";
	
	$conf['cache_t'] = "600";
	
	$ctime = time();
	$sfile = 'config/cache/'.md5($theme).'.txt';
	
	$array = explode(',', $conf['script_f']);
	$array = (!$confs['error_java']) ? array_merge($array, array('plugins/system/block-error.js')) : $array;
	#$array = sort($array, SORT_REGULAR);

	if ($conf['cache_script'] && file_exists($sfile) && filesize($sfile) != 0 && ($ctime - $conf['cache_t']) < filemtime($sfile)) {
		$cont = file_get_contents($sfile);
	} else {
		foreach ($array as $file) {
			#foreach (glob($dir.'*.css') as $file) {
				if (file_exists($file)) {
					if ($conf['script_h']) {
						$cont = file_get_contents($file);
						#$cont = str_replace('../', '', file_get_contents($file));
						#$cont = preg_replace('#url\((\'|"|)(.*?)(\'|"|)\)#i', 'url('.$dir.'\\2)', $cont);
						#if ($en) $cont = preg_replace_callback('#url\((.*?\.(png|jpg|jpeg|gif|bmp))\)#i', 'getImgEncode', $cont);
						
						$arr[] = ($conf['script_c']) ? getCompressCode($cont) : $cont;
						
						#$arr[] = preg_replace('/images\/[-\w\/\.]*/ie','"data:image/".((substr("\\0",-4)==".png")?"png":"gif").";base64,".base64_encode(file_get_contents("\\0"))', file_get_contents($file));
					} else {
						$async = ($conf['script_a']) ? 'async ' : '';
						$arr[] = '<script '.$async.'src="'.$file.'"></script>';
					}
			
					# Замеры найденных файлов
					$handle = fopen($file, 'r');
					$n = 0;
					while (!feof($handle)) {
						$bufer = fread($handle, 1048576);
						$n += substr_count($bufer, "\n") + 1;
					}
					fclose($handle);
					$size = filesize($file);
					$asize += $size;
					$an += $n;
					$f++;
					$content .= '<tr><td>'.$file.'</td><td>Размер: '.round(($size / 1024), 3).' Кб.</td><td>Строк: '.$n.'</td></tr>';
				}
			#}
		}
		$cont = ($conf['script_h']) ? '<script>'.implode(' ', $arr).'</script>' : implode("\n", $arr);
		
		if ($conf['cache_script']) file_put_contents($sfile, $cont);
	}
	
	
	/*
	### New
	$conf['script_a'] = 0;
	###
	$async = ($conf['script_a']) ? 'script_a ' : '';
	$cont = "<script ".$async."src=\"plugins/system/global-func.js\"></script>\n"
	."<script ".$async."src=\"plugins/jquery/jquery.js\"></script>\n"
	."<script ".$async."src=\"plugins/jquery/ui/jquery-ui.js\"></script>\n"
	."<script ".$async."src=\"plugins/jquery/jquery.tablesorter.js\"></script>\n"
	."<script ".$async."src=\"plugins/jquery/jquery.cookie.js\"></script>\n"
	."<script ".$async."src=\"plugins/fancybox/jquery.mousewheel.js\"></script>\n"
	."<script ".$async."src=\"plugins/fancybox/jquery.fancybox.js\"></script>\n"
	."<script ".$async."src=\"plugins/jquery/jquery.slaed.js\"></script>";
	
	$cont .= (!$confs['error_java']) ? "\n<script ".$async."src=\"plugins/system/block-error.js\"></script>" : "";
	
	
	if (file_exists("config/config_header.php")) {
		ob_start();
		include("config/config_header.php");
		$cont .= ob_get_clean();
	}
	*/
	
	$content .= '<tr><td><b>Общее количество файлов: '.$f.'</b></td><td><b>Общий размер: '.round(($asize / 1024), 3).' Кб.</b></td><td><b>Общее количество строк: '.$an.'</b></td></tr></table><br><h3>Содержание массива</h3><textarea cols="225" rows="25">'.$cont.'</textarea><br><h3>Найденные дубликаты</h3>';
	return $content;
}

echo setScript();

####

$cssfiles = "templates/test/, templates/lite/";
$cssfiles = explode(',', $cssfiles);

function setCss($array, $he='', $co='', $en='') {
#$adir = array('templates/lite/style.css', 'plugins/jquery/ui/jquery-ui.css', 'plugins/fancybox/jquery.fancybox.css');
	$content = '';
	$asize = $an = $f = 0;

	$conf['cache_t'] = "600";
	$ctime = time();
	$conf['cache_css'] = "0";
	$cdir = "config/cache/";
	$cfile = $cdir.md5('style').".txt";

	if ($conf['cache_css'] && file_exists($cfile) && filesize($cfile) != 0 && ($ctime - $conf['cache_t']) < filemtime($cfile)) {
		$cont = file_get_contents($cfile);
	} else {
		foreach ($array as $dir) {
			foreach (glob($dir.'*.css') as $file) {
				if (file_exists($file)) {
					if ($he) {
						$cont = str_replace('../', '', file_get_contents($file));
						$cont = preg_replace('#url\((\'|"|)(.*?)(\'|"|)\)#i', 'url('.$dir.'\\2)', $cont);
						if ($en) $cont = preg_replace_callback('#url\((.*?\.(png|jpg|jpeg|gif|bmp))\)#i', 'getImgEncode', $cont);
						if ($co) {
							$arr[] = getCompressCode($cont);
						} else {
							$arr[] = $cont;
						}
						#$arr[] = preg_replace('/images\/[-\w\/\.]*/ie','"data:image/".((substr("\\0",-4)==".png")?"png":"gif").";base64,".base64_encode(file_get_contents("\\0"))', file_get_contents($file));
					} else {
						$arr[] = '<link rel="stylesheet" href="'.$file.'">';
					}
			
					# Замеры найденных файлов
					$handle = fopen($file, 'r');
					$n = 0;
					while (!feof($handle)) {
						$bufer = fread($handle, 1048576);
						$n += substr_count($bufer, "\n") + 1;
					}
					fclose($handle);
					$size = filesize($file);
					$asize += $size;
					$an += $n;
					$f++;
					$content .= '<tr><td>'.$file.'</td><td>Размер: '.round(($size / 1024), 3).' Кб.</td><td>Строк: '.$n.'</td></tr>';
				}
			}
		}
		$cont = ($he) ? '<style type="text/css">'.implode(' ', $arr).'</style>'."\n" : implode("\n", $arr);
		
		if ($conf['cache_css']) file_put_contents($cfile, $cont);
	}
	#$cont = print_r($arr, true);
	$content .= '<tr><td><b>Общее количество файлов: '.$f.'</b></td><td><b>Общий размер: '.round(($asize / 1024), 3).' Кб.</b></td><td><b>Общее количество строк: '.$an.'</b></td></tr></table><br><h3>Содержание массива</h3><textarea cols="225" rows="25">'.$cont.'</textarea><br><h3>Найденные дубликаты</h3>';
	return $content;
}

#echo setCss($cssfiles, 1, 0, 0);

# Convert image to base64 + Cached
function getImgEncode($img) {
	if (file_exists($img[1])) {
		$type = pathinfo($img[1], PATHINFO_EXTENSION);
		static $argc, $cach;
		if ($argc != $img[1] || !isset($cach)) {
			$argc = $img[1];
			$cach = base64_encode(file_get_contents($argc));
		}
		$data = 'url(data:image/'.$type.';base64,'.$cach.')'."\n\n";
	} else {
		$data = 'url('.$img[1].')'."\n\n";
	}
	return $data;
}

# Compress html and text
function getCompressCode($cont) {
	# Удаление табуляторов, пробелов между HTML тегов
	$cont = str_replace(array("\n", "\s", "\r", "\t"), "", $cont);
	$cont = preg_replace("#(?:(?<=\>)|(?<=\/\>))\s+(?=\<\/?)#", "", $cont);
	# Исключение <pre>
	if (false === strpos($cont, "<pre")) $cont = preg_replace("#\s+#", " ", $cont);
	# Удаление новых строк, за которыми пробелы
	$cont = preg_replace("#[\t\r]\s+#", " ", $cont);
	# Сохранение комментариев для IE
	$cont = preg_replace("#<!(--)([^\[|\|])^(<!-->.*<!--.*-->)#", "", $cont);
	# Удаленией комментариев CSS
	$cont = preg_replace("#\/\*.*?\*\/#", "", $cont);
	return $cont;
}


# $ar = array('J. Karjalainen', 'J. Karjalainen', 60, '60', 'J. Karjalainen', 'j. karjalainen', 'Fastway', 'FASTWAY', 'Fastway', 'fastway', 'YUP');
/*
function array_icount_values($array) {
	$ret_array = array();
	foreach ($array as $value) {
		foreach ($ret_array as $key2 => $value2) {
			if (strtolower($key2) == strtolower($value)) {
				$ret_array[$key2]++;
			continue 2;
			}
		}
		$ret_array[$value] = 1;
	}
	return $ret_array;
}

# $a = array_icount_values($ar); // Case-insensitive matching
$a = array_count_values(array_map('mb_strtolower', $ar));
# $a = array_count_values($ar); // Normal matching

# print_r($ar0);
# print_r($ar1);
# print_r($ar2);

arsort($a);
$i = 0;
foreach ($a as $key => $v) {
	if ($v > 1) {
		echo $v.' повторов: '.$key.'<br>';
		$i++;
	}
}
*/
$i = 0;
echo '<br><b>Общее количество строк с повторами: '.$i.'<br><br>Время: '.(microtime(true) - $time_start).' сек., Память: '.(memory_get_usage() / 1048576).' Мб.</b></pre></body></html>';
?>