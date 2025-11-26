<?php
/***************************************************************************\
| Sypex Dumper Pro           version 2.3.2                                  |
| (c) 2003-2018 zapimir      zapimir@zapimir.net       http://sypex.net/    |
| (c) 2005-2018 BINOVATOR    info@sypex.net                                 |
|---------------------------------------------------------------------------|
|     created: 2003.09.02 19:07              modified: 2020.04.28 15:08     |
|---------------------------------------------------------------------------|
| Sypex Dumper is released under the terms of the BSD license               |
|   http://sypex.net/bsd_license.txt                                        |
\***************************************************************************/
header("Expires: Wed, 19 Nov 2008 19:19:19 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL);
//error_reporting(0);

set_error_handler('sxd_error_handler');
register_shutdown_function('sxd_shutdown');
$SXD = new SypexDumper();
chdir(dirname(__FILE__));
$SXD->init(!empty($argc) && $argc > 1 ? $argv : false);
 
class SypexDumper {

	const INC = '.sx/';
	public $name = 'Sypex Dumper Pro 2.3.2';

	public $LNG;
	public $CFG;
	public $SES;
	public $JOB;
	public $VAR;
	public $lng_list;
	protected $mysql;
	protected $langs;
	public $try = false;
	protected $virtualize = false;
	protected $cron_mode = false;
	public $error = false;


	public $fh_rtl;
	public $fh_log;
	public $fh_tmp;
	public $rtl;
	protected $db;
	protected $l;


	public function __construct() {
		define('V_SXD', 20302);
		define('V_PHP', $this->verToInt(phpversion()));
		if(V_PHP < 50203) trigger_error('PHP 5.2+ only', E_USER_ERROR);
		define('C_DEFAULT', 1);
		define('C_RESULT', 2);
		define('C_ERROR', 3);
		define('C_WARNING', 4);
		define('TIMER', microtime(1));
		$this->url = isset($_SERVER["HTTPS"]) ? ($_SERVER["HTTPS"] === 'on' ? 'https://' : 'http://') . getenv("HTTP_HOST") . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/' : '';
	}
	protected function loadLang($lng_name = 'auto'){
		if($lng_name == 'auto'){
			include('lang/list.php');
			$this->langs = &$langs;
			$lng = 'en';
			if(preg_match_all('/[a-z]{2}(-[a-z]{2})?/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $m)) {
				foreach($m[0] AS $l){
					if(isset($langs[$l])){
						$lng_name = $l;
						break;
					}
				}
			}
		}
		if(file_exists("lang/lng_{$lng_name}.php")) include("lang/lng_{$lng_name}.php");
		else include("lang/lng_en.php");
		$this->LNG = &$LNG;
		$this->LNG['name'] = $lng_name;
		return true;
	}
	public function init($args = false){
		$CFG = array();
		include('cfg.php');
		define('SXD_DEBUG', $CFG['debug']);
		$this->loadLang($CFG['lang']);
		if (!ini_get('safe_mode') && function_exists('set_time_limit') && strpos(ini_get('disable_functions'), 'set_time_limit') === false) @set_time_limit($CFG['time_web']);
		elseif (ini_get('max_execution_time') < $CFG['time_web']) $CFG['time_web'] = ini_get('max_execution_time');
		$this->CFG = &$CFG;
		$this->try = false;
		$this->virtualize = false;
		$this->cron_mode = false;
		// Проверяем авторизацию и делаем коннект к базе
		if(empty($this->CFG['my_user'])){
			$this->CFG['my_host'] = 'localhost';
			$this->CFG['my_port'] = 3306;
			$this->CFG['my_user'] = 'root';
			$this->CFG['my_pass'] = '';
			$this->CFG['my_comp'] = 0;
			$this->CFG['my_db'] = '';
		}
//	print_r($args);exit;
		if ($args) { // консольный режим
		    foreach($args AS $key => $arg){
		        if (preg_match("/^-([hupoj])=(.*?)$/", $arg, $m)){
		            switch ($m[1]) {
		                case 'h': $this->CFG['my_host'] = $m[2]; break; // хост
		                case 'o': $this->CFG['my_port'] = $m[2]; break; // порт
		                case 'u': $this->CFG['my_user'] = $m[2]; break; // логин
		                case 'p': $this->CFG['my_pass'] = $m[2]; break; // пароль
		                case 'j': $this->CFG['sjob'] = $m[2]; break; // job-файл
		            }
		        }
		    }
		    $this->cron_mode = true;
		    set_time_limit($CFG['time_cron']);
		    // Загружаем конфиг файл, если нужно
		    $auth = $this->connect();
		    if($auth && !empty($this->CFG['sjob'])){
				$this->ajax($this->loadJob($this->CFG['sjob']));
				echo file_get_contents($this->JOB['file_log']);
				if(file_exists($this->JOB['file_log'])) unlink($this->JOB['file_log']);
				if(file_exists($this->JOB['file_rtl'])) unlink($this->JOB['file_rtl']);
		    }
		    else echo 'Auth error';
		    exit;
		}
		elseif(!empty($this->CFG['auth'])){ // Авторизация
			$auth = false;
			$sfile = 'ses.php';
			
			if(!empty($_COOKIE['sxd']) && preg_match('/^[\da-f]{32}$/', $_COOKIE['sxd'])){
				include($sfile);
				if(isset($SES[$_COOKIE['sxd']])) {
					$auth = true;
					$this->CFG = $SES[$_COOKIE['sxd']]['cfg'];
					$this->SES = &$SES;
					$this->loadLang($this->CFG['lang']);
				}
			}
			if(!$auth) {
				$user = !empty($_POST['user']) ? $_POST['user'] : '';
				$pass = !empty($_POST['pass']) ? $_POST['pass'] : '';
				$host = !empty($_POST['host']) ? $_POST['host'] : (!empty($this->CFG['my_host']) ? $this->CFG['my_host'] : 'localhost');
				$port = !empty($_POST['port']) && is_numeric($_POST['port']) ? (int)$_POST['port'] : 3306;
				$temp = preg_split('/\s+/', $this->CFG['auth']);
				if(!empty($_REQUEST['lang']) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_REQUEST['lang'])) {$this->loadLang($_REQUEST['lang']);}
				foreach($temp AS $a){
					switch($a) {
						case 'cfg': 	if(empty($user)) {continue 2;}
										$auth = !empty($CFG['user']) && isset($CFG['pass']) && $CFG['user']== $user && $CFG['pass'] == $pass;
										break;
						case 'mysql':	if(empty($user)) {continue 2;}
//										if($host != 'localhost' && !empty($this->CFG['my_host']) && $this->CFG['my_host'] != $host) {continue;}
//							echo PHP_INT_MAX;
										$auth = $this->connect($host, $port, $user, $pass);
										break;
						default:		$file = 'auth_' . $a . '.php';
										if(!file_exists($file)) continue 2;
										include	$file;
					}
					if($auth) break;
				}
				if($auth){
					$key = md5(mt_rand(1,100000) . $user . microtime());
					$CFG['lang'] = $this->LNG['name'];
					$_COOKIE['sxd'] = $key;
					$this->saveCFG();
					setcookie('sxd', $key, !empty($_POST['save']) ? time() + 31536000 : 0, '', '', false, true);
					header("Location: {$this->url}?");
					exit;
				}
				foreach(array('user', 'pass', 'host', 'port') AS $key){
					$_POST[$key] = !empty($_POST[$key]) ? htmlspecialchars($_POST[$key], ENT_NOQUOTES) : '';
				}
				$_POST['save'] = !empty($_POST['save']) ? ' CHECKED' : '';
			}
			if (!$auth) {
				if(!empty($_POST['ajax'])){
					echo "sxd.hideLoading();alert('Session not found');";
					exit;
				}
				$this->lng_list = '<option value="auto">- auto -</opinion>';
				if(!isset($this->langs)) {include('lang/list.php');$this->langs = &$langs;}
				foreach($this->langs AS $k => $v){
					$this->lng_list .= "<option value=\"{$k}\"" . ($k == (!empty($_REQUEST['lang']) ? $this->LNG['name'] : $this->CFG['lang']) ? ' SELECTED' : '') . ">{$v}</opinion>";
				}
				include(self::INC . 'tmpl.php');
				echo sxd_tpl_auth();
				exit;
			}

		}
		if(empty($_POST['ajax']['act']) || $_POST['ajax']['act'] != 'save_connect') $this->connect();
		if(isset($_POST['ajax'])) $this->ajax($_POST['ajax']);
		else {
            $this->main();
        }
		exit;
	}
	protected function connect($host = null, $port = null, $user = null, $pass = null){
		$this->error = '';
		$this->try = true;
		if(!empty($user) && isset($pass)) {
			$this->CFG['my_host'] = $host;
			$this->CFG['my_port'] = $port;
			$this->CFG['my_user'] = $user;
			$this->CFG['my_pass'] = $pass;
		}
		$this->mysql = new mysqli(($this->CFG['my_host'][0] != ':' ? $this->CFG['my_host'] : null), $this->CFG['my_user'], $this->CFG['my_pass'], '', $this->CFG['my_port']);
		if(!$this->mysql->connect_error) {
//		if(mysql_connect($this->CFG['my_host'] . ($this->CFG['my_host']{0} != ':' ? ":{$this->CFG['my_port']}" : ''),  $this->CFG['my_user'], $this->CFG['my_pass'])) {
			$this->mysql->set_charset('utf8') or sxd_my_error();
            define('V_MYSQL', $this->verToInt($this->mysql->server_info));
		}
		else {
			//echo mysql_error();
			define('V_MYSQL', 0); 
			$this->error = "sxd.actions.tab_connects();alert(" . sxd_esc($this->mysql->error) . ");";
		}
//		file_put_contents('a:/my.log', V_SXD . ' ' . V_MYSQL . ' ' . V_PHP);
		$this->try = false;
		return V_MYSQL ? true: false;
	}
	protected function main(){
   		// Тулбар
		$this->VAR['toolbar'] = sxd_php2json(
			array(
				array('backup', $this->LNG['tbar_backup'], 1, 3),
				array('restore', $this->LNG['tbar_restore'], 2, 3),
				array('|'),
				array('files', $this->LNG['tbar_files'], 3, 1), 
				array('services', $this->LNG['tbar_services'], 5, 1),
				array('|'),
				array('createdb', $this->LNG['tbar_createdb'], 7, 0),
				array('connects', $this->LNG['tbar_connects'], 6, 0),
				array('|'),
				array('options', $this->LNG['tbar_options'], 4, 1),
				array('|'),
				array('exit', $this->LNG['tbar_exit'], 8, 1),
			)
		);
  		$this->db = 'temp';
		$zip = array($this->LNG['zip_none']);
		if (function_exists("gzopen")) {
			for($i = 1; $i <10; $i++){
				$zip[] = "GZip: {$i}";
			}
			$zip[1] .= " ({$this->LNG['zip_min']})";
			$zip[7] .= " ({$this->LNG['default']})";
		}
		if (function_exists("bzopen")) {
		    $zip[10] = "BZip";
		}
		end($zip);
		$zip[key($zip)] .=  " ({$this->LNG['zip_max']})";
		$this->VAR['combos'] =
			$this->addCombo('backup_db', $this->db, 11, 'db', array()/*$this->getDBList()*/) .
			$this->addCombo('backup_charset', 0, 9, 'charset', $this->getCharsetList()) .
			$this->addCombo('backup_zip', 7, 10, 'zip', $zip) .
			$this->addCombo('restore_db', $this->db, 11, 'db') . 
			$this->addCombo('restore_charset', 0, 9, 'charset') . 
			$this->addCombo('restore_file', 0, 12, 'files', $this->getFileList()) . 
			$this->addCombo('restore_type', 0, 13, 'types', array("CREATE + INSERT ({$this->LNG['default']})", 'TRUNCATE + INSERT', 'REPLACE', 'INSERT IGNORE')) .
			$this->addCombo('services_db', $this->db, 11, 'db') .
			$this->addCombo('services_check', 0, 5, 'check', array("- {$this->LNG['default']} -", 'QUICK', 'FAST', 'CHANGED', 'MEDIUM', 'EXTENDED')) .
			$this->addCombo('services_repair', 0, 5, 'repair', array("- {$this->LNG['default']} -", 'QUICK', 'EXTENDED')) .
			$this->addCombo('services_charset', 0, 9, 'collation', $this->getCollationList()) .
			$this->addCombo('services_charset_col', 0, 15, 'collation:services_charset').
			$this->addCombo('db_charset', 0, 9, 'collation') .
			$this->addCombo('db_charset_col', 0, 15, 'collation:db_charset')
		;
		if (!V_MYSQL) $this->VAR['combos'] .= $this->error;
		$this->VAR['combos']   .= $this->getSavedJobs() . "sxd.confirms = {$this->CFG['confirm']};sxd.actions.dblist();";
        $this->LNG['del_date']  = sprintf($this->LNG['del_date'], '<input type="text" id="del_time" class=txt style="width:24px;" maxlength="3">');
        $this->LNG['del_count'] = sprintf($this->LNG['del_count'], '<input id="del_count" type="text" class=txt style="width:18px;" maxlength="2">');
		$this->LNG['prefix']    = sprintf($this->LNG['prefix'], '<input type="text" id="prefix_from" class=txt style="width:30px;">', '<input type="text" id="prefix_to" class=txt style="width:30px;">');
		include(self::INC . 'tmpl.php');
		echo sxd_tpl_page();
	}
	public function addCombo($name, $sel, $ico, $opt_name, $opts = ''){
		$opts = !empty($opts) ? "{{$opt_name}:" . sxd_php2json($opts) . '}' : "'{$opt_name}'";
		return "sxd.addCombo('{$name}', '{$sel}', {$ico}, {$opts});\n";
	}
	public function ajax($req){
		$res = '';
		$act = $req['act'];
		if($req['act'] == 'run_savedjob'){
			$req = $this->loadJob($req);
		}
		switch($req['act']){
			case 'load_db': 
				$res = $this->getObjects(str_replace('_db', '', $req['name']), $req['value']);
				break;
			case 'load_files': 
				$res = $this->getFileObjects('restore', $req['value']);
				break;
			case 'filelist': 
				$res = "sxd.clearOpt('files');sxd.addOpt(" . sxd_php2json(array('files' => $this->getFileList())) . ");";
				break;
			case 'dblist':
				$res = "sxd.clearOpt('db');sxd.addOpt(" . sxd_php2json(array('db' => $this->getDBList())) . ");sxd.combos.restore_db.select(0,'-');sxd.combos.services_db.select(0,'-');sxd.combos.backup_db.select(0,'-');";
				break;
			case 'load_connect':
				$CFG = $this->cfg2js($this->CFG); 
				$res = "z('con_host').value = '{$CFG['my_host']}', z('con_port').value = '{$CFG['my_port']}', z('con_user').value = '{$CFG['my_user']}',
			z('con_pass').value = '', z('con_comp').checked = {$CFG['my_comp']}, z('con_db').value = '{$CFG['my_db']}', z('con_pass').changed = false;" ;
				break;
			case 'save_connect': 
				$res = $this->saveConnect($req);
				break;
			case 'save_job': 
				unset($req['act']);
				$this->saveJob('sj_' . $req['job'] , $req);
				$res = $this->getSavedJobs();
				break;
			case 'add_db': 
				$res = $this->addDb($req);
				break;
			case 'load_options':
				$CFG = $this->cfg2js($this->CFG);
				$res = "z('time_web').value = '{$CFG['time_web']}', z('time_cron').value = '{$CFG['time_cron']}', z('backup_path').value = '{$CFG['backup_path']}',
			z('backup_url').value = '{$CFG['backup_url']}', z('globstat').checked = {$CFG['globstat']}, z('outfile_path').value = '{$CFG['outfile_path']}', z('outfile_size').value = '{$CFG['outfile_size']}', z('charsets').value = '{$CFG['charsets']}', z('only_create').value = '{$CFG['only_create']}', z('auth').value = '{$CFG['auth']}', z('conf_import').checked = {$CFG['confirm']} & 1, z('conf_file').checked = {$CFG['confirm']} & 2, z('conf_db').checked = {$CFG['confirm']} & 4, z('conf_truncate').checked = {$CFG['confirm']} & 8, z('conf_drop').checked = {$CFG['confirm']} & 16;sxd.confirms = {$this->CFG['confirm']};";
				break;
			case 'save_options': 
				$this->saveOptions($req);
				break;
			case 'delete_file':
				if(preg_match('/^[^\/]+?\.sql(\.(gz|bz2))?$/', $req['name'])) {
					$file = $this->CFG['backup_path'] . $req['name'];
					if(file_exists($file)) unlink($file);
				}
				$res = $this->getFileListExtended();
				break;
			case 'delete_db':
				$res = $this->deleteDB($req['name']);
				break;
			case 'load_files_ext': 
				$res .= $this->getFileListExtended();
				break;
			case 'services': 
				$this->runServices($req);
				break;	
			case 'backup': 
				$this->addBackupJob($req);
				break;
			case 'restore': 
				$this->addRestoreJob($req);
				break;
			case 'resume': 
				$this->resumeJob($req);
				break;
			case 'exit': 
				setcookie('sxd', '', 0);
				$res = "top.location.href = " . sxd_esc($this->CFG['exitURL']) . ";";
				break;
		}	
		echo $res;
	}
	protected function loadJob($job){
		$file = $this->CFG['backup_path'] . 'sj_' . (is_array($job) ? $job['job'] : $job) . '.job.php';
		if(!file_exists($file)) return;
		include($file);//error_log(var_export($JOB,1). "\n", 3, 'backup/error.log');
		$JOB['act'] = $JOB['type'];
		$JOB['type'] = 'run';
		return $JOB;
	}
	protected function deleteDB($name){
		$r = $this->mysql->query('DROP DATABASE `' . sxd_esc($name, false) . '`') or sxd_my_error();
		if($r){
        	return "sxd.clearOpt('db');sxd.addOpt(" . sxd_php2json(array('db' => $this->getDBList())) . ");sxd.combos.services_db.select(0,'-');";
		}
        else
			return "alert(" . sxd_esc($this->mysql->error) . ");";
	}
	protected function cfg2js($cfg){
		foreach($cfg AS $k => $v){
			$cfg[$k] = is_array($v) ? $this->cfg2js($v) : sxd_esc($v, false);
		}
		return $cfg;
	}
	protected function addDb($req){
        $r = $this->mysql->query('CREATE DATABASE `' . sxd_esc($req['name'], false) . '`' . (V_MYSQL > 40100 ? "CHARACTER SET {$req['charset']} COLLATE {$req['collate']}" : ''));
        if($r)
        	return "sxd.addOpt(" . sxd_php2json(array('db' => array($req['name'] => "{$req['name']} (0)"))) . ");";
        else
        	 sxd_my_error();
	}
	protected function saveConnect($req){
		$this->CFG['my_host'] = $req['host'];
		$this->CFG['my_port'] = (int)$req['port'];
		$this->CFG['my_user'] = $req['user'];
		if(isset($req['pass'])) $this->CFG['my_pass'] = $req['pass'];
		$this->CFG['my_comp'] = $req['comp'] ? 1 : 0;
		$this->CFG['my_db']   = $req['db'];
		$this->saveCFG();
        $this->connect();
        if (V_MYSQL) {
	        $tmp = array(
        		'db' => $this->getDBList(),
        		'charset' => $this->getCharsetList(),
        		'collation' => $this->getCollationList()
			);
	        return "sxd.clearOpt('db');sxd.clearOpt('charset');sxd.clearOpt('collation');sxd.addOpt(" . sxd_php2json($tmp) . ");sxd.combos.backup_db.select(0,'-');sxd.combos.restore_db.select(0,'-');sxd.combos.services_db.select(0,'-');sxd.combos.backup_charset.select(0,'-');sxd.combos.services_db.select(0,'-');sxd.combos.db_charset.select(0,'-');";
		}
		else {
			return $this->error;
		}
	}
	protected function saveOptions($req){
		$this->CFG['time_web']    = $req['time_web'];
		$this->CFG['time_cron']   = $req['time_cron'];
		$this->CFG['backup_path'] = $req['backup_path'];
		$this->CFG['backup_url']  = $req['backup_url'];
		$this->CFG['globstat']    = $req['globstat'] ? 1 : 0;
		$this->CFG['outfile_path']= $req['outfile_path'];
		$this->CFG['outfile_size']= $req['outfile_size'];
		$this->CFG['charsets']    = $req['charsets'];
		$this->CFG['only_create'] = $req['only_create'];
		$this->CFG['auth']        = $req['auth'];
		$this->CFG['confirm']     = $req['confirm'];
		$this->saveCFG();
	}
	protected function saveCFG(){
		if (isset($_COOKIE['sxd'])) {
			$this->SES[$_COOKIE['sxd']] = array('cfg' => $this->CFG, 'time' => time(), 'lng' => $this->LNG['name']);
			file_put_contents('ses.php', "<?php\n\$SES = " . var_export($this->SES, 1) . ";");
		}
        if (!$this->virtualize){
			file_put_contents('cfg.php', "<?php\n\$CFG = " . var_export($this->CFG, 1) . ";");
        }
	}
	protected function runServices($job) {
		$serv = array('optimize' => 'OPTIMIZE', 'analyze' => 'ANALYZE', 'check' => 'CHECK', 'repair' => 'REPAIR');
		$add = array('check'  => array('', 'QUICK', 'FAST', 'CHANGED', 'MEDIUM', 'EXTENDED'), 'repair' => array('', 'QUICK', 'EXTENDED'));
		if(isset($serv[$job['type']])) {
			$this->mysql->select_db($job['db']);
			$filter = $object = array();
			$this->createFilters($job['obj'], $filter, $object);
			$r = $this->mysql->query('SHOW TABLE STATUS') or sxd_my_error();
			if (!$r) return;
			$tables = array();
			while($item = $r->fetch_assoc()){
				if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) continue;
				if(sxd_check($item['Name'], $object['TA'], $filter['TA'])) $tables[] = "`{$item['Name']}`";
			}
			$sql = $serv[$job['type']] . ' TABLE ' . implode(',', $tables);
			
			if ($job['type'] == 'check' || $job['type'] == 'repair') {
				$sql .= isset($add[$job['type']][$job[$job['type']]]) ? ' ' . $add[$job['type']][$job[$job['type']]] : '';
			}
			
			$r = $this->mysql->query($sql) or sxd_my_error();
			if (!$r) return;
			$res = array();
			while($item = $r->fetch_row()){
				$res[] = $item;
			}
			echo 'sxd.result.add(' . sxd_php2json($res). ');';
		}
		
		// ADDED 2.0.7 PRO
		elseif(in_array($job['type'], array('convert', 'correct', 'enable_keys', 'disable_keys', 'truncate', 'drop_tab'))) {
			// Pro-сервисы	
			$this->mysql->select_db($job['db']);
			$filter = $object = array();
			$this->createFilters($job['obj'], $filter, $object);
			$r = $this->mysql->query('SHOW TABLE STATUS') or sxd_my_error();
			if (!$r) return;
			$tables = array();
			while($item = $r->fetch_assoc()){
				if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) continue;
				if(sxd_check($item['Name'], $object['TA'], $filter['TA'])) $tables[] = "`{$item['Name']}`";
			}
			//print_r($tables);
			//echo 'alert(' . var_export($tables) . ')';
			foreach($tables AS $t){
				$type = $job['type'];
				$error = false;
				switch($job['type']){
					case 'convert': // Конвертация кодировки
						if($this->mysql->query("ALTER TABLE {$t} CONVERT TO CHARACTER SET {$job['charset']} COLLATE {$job['collate']}"))  $result = "OK. Convert to `{$job['collate']}`";
						else $error = true;
					break;
					
					case 'correct': // Исправление кодировки
					
						$c = $this->mysql->query("SHOW FULL COLUMNS FROM {$t} WHERE `Collation` IS NOT NULL");
						$ok = true;
						while($col = $this->mysql->fetch_assoc($c)){
							$add = ($col['Null'] == 'YES' ? '' : 'NOT NULL') . (preg_match("/(blob|text)/",
								$col['Type']) ? '' : ' DEFAULT ' . (is_null($col['Default']) ? 'NULL' : sxd_esc($col['Default']) . "'")) . (empty($col['Comment']) ? '' : " COMMENT " . sxd_esc($col['Comment']));
							if($this->mysql->query("ALTER TABLE {$t} CHANGE {$col['Field']} {$col['Field']} {$col['Type']} CHARACTER SET binary {$add}")){
								if($this->mysql->query("ALTER TABLE {$t} CHANGE `{$col['Field']}` `{$col['Field']}` {$col['Type']} CHARACTER SET {$job['charset']} COLLATE {$job['collate']} {$add}")) {}
								else {$ok = false;break;}
							}
							else {$ok = false;break;}
						}
						if ($ok){
							$this->mysql->query("ALTER TABLE {$t} DEFAULT CHARACTER SET {$job['charset']} COLLATE {$job['collate']}");
							$result = "OK. Correct to `{$job['collate']}`";
						}
						else $error = true;

					break;
					
					case 'enable_keys': // Включение индексов
						$type = 'enable keys';
						if($this->mysql->query("ALTER TABLE {$t} ENABLE KEYS")) $result = 'OK';
						else $error = true;
					break;
					
					case 'disable_keys': // Включение индексов
						$type = 'disable keys';
						if($this->mysql->query("ALTER TABLE {$t} DISABLE KEYS")) $result = 'OK';
						else $error = true;
					break;
					
					case 'truncate': // Очистка таблицы
						if($this->mysql->query("TRUNCATE {$t}")) $result = 'OK';
						else $error = true;
					break;
					
					case 'drop_tab': // Удаление таблицы
						if($this->mysql->query("DROP TABLE {$t}")) $result = 'OK';
						else $error = true;
					break;
				}
				if($error)	echo "sxd.result.add(['{$t}', '{$type}', 'error', " . sxd_esc($this->mysql->error) . "]);";
				else 		echo "sxd.result.add(['{$t}', '{$type}', 'status', '{$result}']);";
				if(in_array($type, array('truncate', 'drop_tab'))) echo "sxd.combos.services_db.action();";
			}
			
		}
		///
	}
	protected function createFilters(&$obj, &$filter, &$object){
		$types = array('TA', 'TC', 'VI', 'PR', 'FU', 'TR', 'EV');
		foreach($types AS $type){
			$filter[$type] = array();
			$object[$type] = array();
			if(!empty($obj[$type])){
				foreach($obj[$type] AS $v){
					if(strpos($v, '*') !== false) {
						$filter[$type][] = str_replace('*', '.*?', $v); 
					}
					else {
						$object[$type][$v] = true;
					}
				}
				$filter[$type] = count($filter[$type]) > 0 ? '/^(' . implode('|', $filter[$type]) . ')$/i' : '';
			}
		}
	}
	protected function closeConnect(){
	//	return;
		ignore_user_abort(true);
		header("SXD: {$this->name}");
//		echo '1323;';
//		$size = ob_get_length();
//		header('Content-Length: ' . $size);
//		header("Connection: close");
//		if ($size) ob_end_flush();
//		@flush();
		if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
	}
	protected function resumeJob($job){
		$this->closeConnect();
		include($this->CFG['backup_path'] . $job['job'] . '.job.php');
		$this->JOB = &$JOB;
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'r+b');
		$this->fh_log = fopen($this->JOB['file_log'], 'ab');
		$t = fgets($this->fh_rtl);
		if(!empty($t)){
			$this->rtl = explode("\t", $t);	
		}
		else {
			$this->addLog($this->LNG['not_found_rtl']);
			exit;
		}
		//
		//$this->rtl[6] = '';
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		$this->rtl[9] = 0;
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		if ($this->JOB['act'] == 'backup') $this->runBackupJob(true);
		elseif ($this->JOB['act'] == 'restore') $this->runRestoreJob(true);
	}
	protected function addRestoreJob($job) {
		$this->closeConnect();
		$this->JOB = $job;
		// Создаем список объектов и фильтр
		$filter = $object = array();
   		$this->createFilters($this->JOB['obj'], $filter, $object);
		$objects = $this->getFileObjects('restore', $this->JOB['file'], false);
		$todo = array();
		$rows = 0;
		$this->tab_rows = array();
		$todo = array();
		foreach($objects AS $t => $list){
			if($t == 'TA' && (!empty($object['TC']) || !empty($filter['TC']))) {}
			elseif(empty($object[$t]) && empty($filter[$t])) {continue;}
			if (empty($list)) continue;
			
			foreach($list AS $item){
				switch($t){
					case 'TA':
						$type = '';
						if(sxd_check($item[0], $object['TA'], $filter['TA'])){
							$type = empty($item[1]) ? 'TC' : 'TA';
						}
						elseif(sxd_check($item[0], $object['TC'], $filter['TC'])) {
							$type = 'TC';
						}
						else {$todo[] = array('TA', $item[0], 'SKIP'); continue 2;}
						$todo[]   = array($type, $item[0], $item[1], $item[2]);
						$rows += $type == 'TA' ? $item[1] : 0;
					break;
					default:
						if(sxd_check($item, $object[$t], $filter[$t])) {
							$todo[] = array($t, $item, '');
							$skip = false;
						}
						else {
							$todo[] = array($t, $item, 'SKIP');
						}
				}
			}
		}
		$this->JOB['file_tmp'] = $this->JOB['file_name'] = $this->CFG['backup_path'] . $this->JOB['file'];
		$this->JOB['file_rtl'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.rtl';
		$this->JOB['file_log'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.log';
		$this->JOB['file_stp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.stp';
		if(!empty($this->JOB['prefix_from'])) preg_quote($this->JOB['prefix_from']);
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
		
		$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'r');
		// Добавлено в 2.0.9 pro
		// Для чужих дампов определяем разделители строк
		if(is_null($this->JOB['obj'])) {
			$s = fread($this->fh_tmp, 2048);
			if(strpos($s, "\r\n")) $this->JOB['eol'] = "\r\n";
			elseif(strpos($s, "\n")) $this->JOB['eol'] = "\n";
			else $this->JOB['eol'] = "\r";
			$bom = strncmp($s, "\xEF\xBB\xBF", 3) == 0 ? 3 : ((strncmp($s, "\xFE\xFF", 2) == 0 || strncmp($s, "\xFF\xFE", 2) == 0) ? 2 : 0);
			fseek($this->fh_tmp, $bom);
		}
		$this->JOB['todo'] = $todo;
		$this->saveJob($this->JOB['job'], $this->JOB);
		
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'wb');
		$this->fh_log = fopen($this->JOB['file_log'], 'wb');
		$this->rtl = array(time(), time(), $rows, 0, '', '', '', 0, 0, 0, 0, TIMER, "\n");
		$this->addLog(sprintf($this->LNG['restore_begin'], $this->JOB['db']) . ($this->JOB['savesql'] ? "{$this->LNG['infile']} `*.sxd.sql`": ''));
		$this->addLog("{$this->LNG['combo_file']} {$this->JOB['file']}");
		$this->runRestoreJob();
	}
	protected function runRestoreJob($continue = false){
		$ei = false;
		if($continue){
			$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'r');
			fseek($this->fh_tmp, $this->rtl[3]);
			if(!empty($this->rtl[6])) $this->setNames($this->JOB['correct'] == 1 && !empty($this->JOB['charset']) ? $this->JOB['charset'] : $this->rtl[6]);
			if($this->rtl[7] < $this->rtl[10]) $ei = true; 
		}
		$this->mysql->select_db($this->JOB['db']);
		if(is_null($this->JOB['obj'])) $this->runRestoreJobForeign($continue);
		//$this->mysql->query("SET NAMES 'UTF8'");
		$types = array('VI' => 'View', 'PR' => 'Procedure', 'FU' => 'Function', 'TR' => 'Trigger', 'EV' => 'Event');
		$fcache = '';
		$writes = 0;
		$old_charset = '';
		$tab = '';
		$seek = 0;
		$this->rtl[3] = ftell($this->fh_tmp);
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$c = 0;
		switch($this->JOB['strategy']){
			case 1: $tc = 'TRUNCATE'; $td = 'INSERT'; break;
			case 2: $tc = ''; $td = 'REPLACE'; break;
			case 3: $tc = ''; $td = 'INSERT IGNORE'; break;
			default: $tc = 'DROP TABLE IF EXISTS'; $td = 'INSERT';
		}
		$tab_exists = array();
		if($this->JOB['strategy'] > 0){
			$r = $this->mysql->query("SHOW TABLES") or sxd_my_error();
			while($item = $r->fetch_row()){
				$tab_exists[$item[0]] = true;
			}
		}
		// Перенаправление вывода (в mysql/файл)
		$this->query = $query = $this->JOB['savesql'] ? array(this, 'save_query') : array($this->mysql, 'query'); // ADDED 2.0.7 PRO
//		$this->query = $query = $this->JOB['savesql'] ? 'save_query' : 'mysql_query'; // ADDED 2.0.7 PRO


		if($this->JOB['savesql'] && file_exists($this->JOB['file_name'] . '.sxd.sql')) unlink($this->JOB['file_name'] . '.sxd.sql');
		
		$insert = $continue && $this->rtl[7] < $this->rtl[10] ? "{$td} INTO `{$this->rtl[5]}` VALUES " : '';
		//$enable_index = array();
		if(V_MYSQL > 40014) {
			$query("SET UNIQUE_CHECKS=0");
			$query("SET FOREIGN_KEY_CHECKS=0");
			$query("SET autocommit=0;");
			if(V_MYSQL > 40101) $query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
			if(V_MYSQL > 40111) $query("SET SQL_NOTES=0");
		}
		$log_sql = false;
		$fields = '';
		$time_old = time();
		$exit_time = $time_old + $this->CFG['time_web'] - 1;
		$prefix = empty($this->JOB['prefix_from']) || empty($this->JOB['prefix_to']) ? false : true; // ADDED 2.0.7 PRO
		//@unlink('skip.log');
		// ADDED 2.0.7 PRO
		$skipto = $this->skipper($this->JOB['todo'][0][0]);
		while($q = sxd_read_sql($this->fh_tmp, $seek, $ei, $skipto)){
//			file_put_contents('a:/log.sql', "\n----\n{$q}\n----\n", FILE_APPEND);
			if($time_old < time()) {
				if(file_exists($this->JOB['file_stp'])){
					$type = file_get_contents($this->JOB['file_stp']);
					$this->rtl[9] = !empty($type) ? $type : 2;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					/*if($type == 1) {
						
					}*/
					unset($this->rtl);
					exit;
				}
				$time_old = time();
				if($time_old >= $exit_time){
					$this->rtl[9] = 3;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					unset($this->rtl);
					exit;
				}
				clearstatcache(); 
			}
			switch($q[0]){
				case '(':
					if($continue) {
						$this->addLog(sprintf("{$this->LNG['restore_TC']} {$this->LNG['continue_from']}", $this->rtl[5], $this->rtl[3]));
						$continue = false;
					}
					$q = $insert . $q;
					$ex = 1;
					$c = 1;
					break;	
				case 'I':
					if (preg_match('/^INSERT( INTO `(.+?)`) VALUES/', $q, $m)) {
						$insert = $td . $m[1] . $fields . " VALUES \n";
						$tab = $m[2];
						$this->rtl[7] = 0;
						$this->rtl[8] = 0;
						foreach($this->JOB['todo'] AS $t){
							if($t[1] == $tab) {
								$this->rtl[8] = $t[2];
							}	
						}
						if($prefix) {
							$insert = preg_replace("/`{$this->JOB['prefix_from']}(.+?)`/", "`{$this->JOB['prefix_to']}\\1`", $insert);
							$tab = preg_replace("/^{$this->JOB['prefix_from']}(.+?)/", "{$this->JOB['prefix_to']}\\1", $tab);
							$q = substr_replace($q, $insert, 0, strlen($m[0])+2);
						}
						elseif($this->JOB['strategy']) {
							$q = substr_replace($q, $insert, 0, strlen($m[0])+2);
						}
						//$this->mysql->query("LOCK TABLES `{$tab}` WRITE") or die ($this->mysql->error);
						$query("ALTER TABLE `{$tab}` DISABLE KEYS") or sxd_my_error();
						//if(!empty($this->JOB['autoinc'])) $query("ALTER TABLE `{$tab}` AUTO_INCREMENT = 1") or sxd_my_error();
						$ex = 1;
					}
					break;
				case 'C':
					$ex = 1;
//					$this->setNames('utf8');
					if (preg_match('/^CREATE TABLE `/', $q)) {
						if($this->JOB['strategy'] != 0 && isset($tab_exists[$this->rtl[5]])) $ex = 0;
						else {
							$ex = 1;
							if($prefix) {
								$q = preg_replace("/^CREATE TABLE `{$this->JOB['prefix_from']}(.+?)` \(/", "CREATE TABLE `{$this->JOB['prefix_to']}\\1` (", $q);
							}
							if((!empty($this->JOB['correct']) && !empty($this->JOB['charset']))){
									$q = preg_replace('/(DEFAULT)?\s*(CHARSET|CHARACTER SET|COLLATE)[=\s]+\w+/i', '', $q) . (V_MYSQL < 40100 ? '' : ' DEFAULT CHARSET=' . $this->JOB['charset']);
								}
							if(!empty($this->JOB['autoinc'])) $q = preg_replace("/AUTO_INCREMENT=\d+/", "AUTO_INCREMENT=1", $q);
						}
						// Достаем имена полей таблицы
						$fields = $this->JOB['strategy'] > 0 && preg_match_all('/^\s+(`.+?`) /m', $q, $f, PREG_PATTERN_ORDER) ? '(' . implode(',', $f[1]) . ')' : '';
					}
					break;
				case '#': // Команды для дампера
					if (preg_match("/\#\t(TC|TD|VI|PR|FU|TR|EV)`(.+?)`(([^_]+?)_.+?)?$/", $q, $m)) {
						//if(!empty($tab)) $enable_index[] = $tab;
						$skipto = $this->skipper($m[1], $m[2]);
						if($skipto) {$ex = 0; continue 2;}
						if($m[1] == 'TD'/* || $m[1] == 'TC'*/) $this->setNames($this->JOB['correct'] == 1 && !empty($this->JOB['charset']) ? $this->JOB['charset'] : (empty($m[3]) ? '' : $m[3])); // Для всех кроме таблиц выставляем кодировку UTF8
                        else {$this->setNames('utf8');}
						$m[2] = preg_replace("/^{$this->JOB['prefix_from']}(.+?)/", "{$this->JOB['prefix_to']}\\1", $m[2]);
						if($m[1] == 'TC') {
							$this->addLog(sprintf($this->LNG['restore_TC'], $m[2]));
							$insert = '';
							$tab = '';
							$this->rtl[4] = 'TD';
							$this->rtl[5] = $m[2];
							$ei = 0;
							if($tc && ($this->JOB['strategy'] == 0 || isset($tab_exists[$m[2]]))) {
								$query("{$tc} `{$m[2]}`") or sxd_my_error();
							}
						}
						elseif($m[1] == 'TD'){
							$ei = 1;
						}
						else {
							$this->rtl[4] = $m[1];
							$this->rtl[5] = $m[2];
							$this->rtl[7] = 0;
							$this->rtl[8] = 0;
							$query("DROP {$types[$m[1]]} IF EXISTS `{$m[2]}`") or sxd_my_error();
							$this->addLog(sprintf($this->LNG["restore_{$m[1]}"], $m[2]));
							$ei = 0;
						}
						
					}
					$ex = 0;
					break;
				default: 
					$insert = '';
					$ex = 1;
			}
			if($ex) {
//				error_log("[index.php]\n---------------------\n{$q}\n", 3, "error.log"); // TODO: удалить логи
                if(V_MYSQL > 80000) $q = str_replace('\N', 'NULL', $q);
				$this->rtl[3] = ftell($this->fh_tmp) - $seek;
				fseek($this->fh_rtl, 0);
				$this->rtl[1] = time();
				fwrite($this->fh_rtl, implode("\t", $this->rtl));
//				error_log("\n---------------------\n{$q}", 3, "sql.log");
				if($query($q)) {
					if($insert) {
						$c = 1;
					}
				}
				else {
					//error_log(date('r') . "\n----------\n{$q}\n", 3, "backup/sql_error.log");
					sxd_my_error();
				}
				
				if($c){
					$i = $this->JOB['savesql'] ? $this->nl_count : $this->mysql->affected_rows;
					$this->rtl[3] = ftell($this->fh_tmp) - $seek;
					$this->rtl[7] += $i;
					$this->rtl[10] += $i;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					$c = 1;
				}
			}
			
		}
		if(!$this->JOB['savesql']){
			// Включаем ключи
			$this->addLog($this->LNG['restore_keys']);
			$this->rtl[4] = 'EK';
			$this->rtl[5] = '';
			$this->rtl[6] = '';
			$this->rtl[7] = 0;
			$this->rtl[8] = 0;
			foreach($this->JOB['todo'] AS $tab){
				if ($tab[0] == 'TA' && $tab[2] != 'SKIP') {
					if($prefix) {
						$tab[1] = preg_replace("/^{$this->JOB['prefix_from']}(.+?)/", "{$this->JOB['prefix_to']}\\1", $tab[1]);
					}
					$query("ALTER TABLE `{$tab[1]}` ENABLE KEYS") or sxd_my_error();
					$query("COMMIT;") or sxd_my_error();
					$this->rtl[1] = time();
					$this->rtl[5] = $tab[1];
					fseek($this->fh_rtl, 0);
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
				}
			}
		}
		else {
			$this->rtl[7] = 0;
			$this->rtl[8] = 0;
		}
		$this->rtl[4] = 'EOJ';
		$this->rtl[5] = round(microtime(1) - $this->rtl[11], 4);
		
		fseek($this->fh_rtl, 0);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$this->addLog(sprintf($this->LNG['restore_end'], $this->JOB['db']));
		fclose($this->fh_log);
		fclose($this->fh_rtl);
	}

    protected function save_query($str){
        $this->nl_count = substr_count($str, "\n");
        return file_put_contents($this->JOB['file_name'] . ".sxd.sql", "{$str};\n", FILE_APPEND);
    }
	protected function runRestoreJobForeign($continue = false){
		$ei = false;
		
		$fcache = '';
		$writes = 0;
		$old_charset = '';
		$tab = '';
		$seek = 0;
		$this->rtl[3] = ftell($this->fh_tmp);
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$c = 0;
		
		$log_sql = false;
		$fields = '';
		$insert = '';
		$last_tab = '';
		$time_old = time();
		$exit_time = $time_old + $this->CFG['time_web'] - 1;
		$delimiter = ";";
		$this->query = array($this->mysql, 'query');
		while($q = sxd_read_foreign_sql($this->fh_tmp, $seek, $ei, $delimiter, $this->JOB['eol'])){
			$q = ltrim($q);

			if(empty($q)) break;
			if($time_old < time()) {
				if(file_exists($this->JOB['file_stp'])){
					$type = file_get_contents($this->JOB['file_stp']);
					$this->rtl[9] = !empty($type) ? $type : 2;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					unset($this->rtl);
					exit;
				}
				$time_old = time();
				if($time_old >= $exit_time){
					$this->rtl[9] = 3;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					unset($this->rtl);
					exit;
				}
				clearstatcache(); 
			}
			
			do {
				$repeat = false;
				if(empty($q)) {
					break 2;
				}
				switch($q[0]){
					case '(':
						if($continue) {
							$this->addLog(sprintf("{$this->LNG['restore_TC']} {$this->LNG['continue_from']}", $this->rtl[5], $this->rtl[3]));
							$continue = false;
						}
						$q = $insert . $q;
						$ex = 1;
						$c = 1;
						break;	
					case 'I':
					//error_log("-----------------\n{$q}\n", 3, "insert.log");
						if (preg_match('/^(INSERT( INTO `(.+?)`).*?\sVALUES)/s', $q, $m)) {
							//error_log("-----------------\n{$q}\n", 3, "insert.log");
							//$this->addLog(sprintf("Отключаем индексы `%s`", var_export($m, 1)));
							$insert = trim($m[1]) . ' ';
							$tab = $m[3];
							$this->rtl[7] = 0;
							$this->rtl[8] = 0;
							if($last_tab != $tab) {
								$this->addLog(sprintf("Отключаем индексы `%s`", $tab));
								$this->mysql->query("ALTER TABLE `{$tab}` DISABLE KEYS") or sxd_my_error();
								//if(!empty($this->JOB['autoinc'])) $this->mysql->query("ALTER TABLE `{$tab}` AUTO_INCREMENT = 1") or sxd_my_error();
								$last_tab = $tab;
							}
							$ex = 1;
						}
						break;
					case 'C':
						$ex = 1;
						$ei = 1;
						if (preg_match('/^CREATE TABLE.+?`(.+?)`/', $q, $m)) {
							$ex = 1;
							$tab = $m[1];
							$this->addLog(sprintf($this->LNG['restore_TC'], $tab));
							$this->mysql->query("DROP TABLE IF EXISTS `{$tab}`");
							if((!empty($this->JOB['correct']) && !empty($this->JOB['charset']))){
								$q = preg_replace('/(DEFAULT)?\s*(CHARSET|CHARACTER SET|COLLATE)[=\s]+\w+/i', '', $q) . (V_MYSQL < 40100 ? '' : ' DEFAULT CHARSET=' . $this->JOB['charset']);
							}
							elseif(empty($this->JOB['charset'])){
								if(preg_match("/(CHARACTER SET|CHARSET)[=\s]+(\w+)/i", $q, $charset)){
									$this->setNames($charset[2]);
								}
							}
						}
						break;
					case '-' && $q[1] == '-':
					case '#':
						$repeat = true;
						$q = ltrim(substr($q, strpos($q, $this->JOB['eol'])));
						$ex = 0;
						break;
					case '/':
					case 'S':
						if (preg_match('/SET NAMES (\w+)/', $q, $m)) {
							$this->JOB['charset'] = $m[1];
							$this->setNames($this->JOB['charset']);
							$ex = 0;
						}
						else $ex = 1;
					break;
					case 'D':
					//error_log("-----------------\n{$q}\n", 3, "insert.log");
						if (preg_match('/^DELIMITER (.+?)\s/s', $q, $m)) {
							
							// Нужно вернуть предыдущий разделитель и eol и прочитать
							$q = ltrim(substr($q, strpos($q, $this->JOB['eol']))) . $delimiter . $this->JOB['eol'];
							// И прочитать остаток запроса с новым разделителем 
							$delimiter = $m[1];
							$this->addLog(sprintf("Установлен разделитель '%s'", $delimiter));
							//error_log("-----------------\n{$q}\n", 3, "delimiter.log");
							$q .= ltrim(sxd_read_foreign_sql($this->fh_tmp, $seek, $ei, $delimiter, $this->JOB['eol']));
							//error_log("-----------------\n{$q}\n", 3, "delimiter.log");
							$delimiter = $m[1];
							//exit;
							$ex = 1;
						}
						else {
							$insert = '';
							$ex = 1;
							$ei = 0;
						}
						break;
					default: 
						$insert = '';
						$ex = 1;
						$ei = 0;
				}
			}  while ($repeat);
			if($ex) {
				//error_log("-----------------\n{$q}\n", 3, "run.log");
				$this->rtl[3] = ftell($this->fh_tmp) - $seek;
				fseek($this->fh_rtl, 0);
				$this->rtl[1] = time();
				fwrite($this->fh_rtl, implode("\t", $this->rtl));
				if($this->mysql->query($q)) {
					if($insert) {
						$c = 1;
					}
				}
				else {
					error_log("-----------------\n{$q}\n", 3, "error.log");
					sxd_my_error();
				}
				
				if($c){
					$i = $this->mysql->affected_rows;
					$this->rtl[3] = ftell($this->fh_tmp) - $seek;
					$this->rtl[7] += $i;
					$this->rtl[10] += $i;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					$c = 1;
				}
			}
			
		}
		
		$this->rtl[4] = 'EOJ';
		$this->rtl[5] = round(microtime(1) - $this->rtl[11], 4);
		$this->rtl[7] = 0;
		$this->rtl[8] = 0;
		
		fseek($this->fh_rtl, 0);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$this->addLog(sprintf($this->LNG['restore_end'], $this->JOB['db']));
		fclose($this->fh_log);
		fclose($this->fh_rtl);
	}
	protected function addBackupJob($job) {
		$this->closeConnect();
		// Создаем новое задание
		$this->JOB = $job;
		$this->mysql->select_db($this->JOB['db']);
		// Создаем список объектов и фильтр
		$filter = $object = array();
		$this->createFilters($this->JOB['obj'], $filter, $object);
		$queries = array(
			array('TABLE STATUS', 'Name', 'TA')
		);
		if (V_MYSQL > 50014) {
			$queries[] = array("PROCEDURE STATUS WHERE db='{$this->JOB['db']}'", 'Name', 'PR');
			$queries[] = array("FUNCTION STATUS WHERE db='{$this->JOB['db']}'", 'Name', 'FU');
			$queries[] = array('TRIGGERS', 'Trigger', 'TR');
			if(V_MYSQL > 50100) $queries[] = array('EVENTS', 'Name', 'EV');
		}
		$todo = $header = array();
		$tabs = $rows = 0;
		$only_create = explode(' ', $this->CFG['only_create']);
		foreach($queries AS $query){
			$t = $query[2];
			if($t == 'TA' && (!empty($object['TC']) || !empty($filter['TC']))) {}
			elseif(empty($object[$t]) && empty($filter[$t])) continue;
			$r = $this->mysql->query('SHOW ' . $query[0]) or sxd_my_error();
			if (!$r) continue;
			$todo[$t] = array();
			$header[$t] = array();
			
			while($item = $r->fetch_assoc()){
				$n = $item[$query[1]];
				switch($t){
					case 'TA':
					case 'TC':
						if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) {
							if(sxd_check($n, $object['VI'], $filter['VI'])){
								$todo['VI'] = array();
								$header['VI']= array();
							}
							continue 2;
						}
						elseif(sxd_check($n, $object['TA'], $filter['TA'])){
							$engine = V_MYSQL > 40101 ? $item['Engine'] : $item['Type'];
							$t = in_array($engine, $only_create) ? 'TC' : 'TA';
						}
						elseif(sxd_check($n, $object['TC'], $filter['TC'])) {
							$t = 'TC';
							$item['Rows'] = $item['Data_length'] = 0;
						}
						else continue 2;
						$todo['TA'][]   = array($t, $n, !empty($item['Collation']) ? $item['Collation'] : '', $item['Auto_increment'], $item['Rows'], $item['Data_length']);
						$header['TA'][] = "{$n}`{$item['Rows']}`{$item['Data_length']}";
						$tabs++;
						$rows += $item['Rows'];
					break;
					default:
						if(sxd_check($n, $object[$t], $filter[$t])) {
							$todo[$t][] = array($t, $n, !empty($item['collation_connection']) ? $item['collation_connection'] : '');
							$header[$t][] = $n;
						}
				}
			}
			
		}
		if (V_MYSQL > 50014 && (!empty($object['VI']) || !empty($filter['VI']))) {
			// Бэкап обзоров, нужно отсортировать зависимые
			$r = $this->mysql->query("SELECT table_name, view_definition /*!50121 , collation_connection */ FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = '{$this->JOB['db']}'") or sxd_my_error();
			$views = $dumped = $views_collation = array();
			$re = "/`{$this->JOB['db']}`.`(.+?)`/";
			while($item = $r->fetch_assoc()){
				preg_match_all($re, preg_replace("/^select.+? from/i", '', $item['view_definition']), $m);
				$used = $m[1];	
				$views_collation[$item['table_name']] = !empty($item['collation_connection']) ? $item['collation_connection'] : '';
				$views[$item['table_name']] = $used;
			}
			
			while (count($views) > 0) {
				foreach($views AS $n => $view) {
					$can_dumped = true;
					foreach($view AS $k) {
						if (isset($views[$k]) && !isset($dumped[$k])) $can_dumped = false;	
					}
					if ($can_dumped) {
						if(sxd_check($n, $object['VI'], $filter['VI'])){
							$todo['VI'][] = array('VI', $n, $views_collation[$n]);
							$header['VI'][] = $n;
						}
						$dumped[$n] = 1;
						unset($views[$n]);
					}
				}
			}
			unset($dumped);
			unset($views);
			unset($views_collation);
		}
		$this->JOB['file_tmp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.tmp';
		$this->JOB['file_rtl'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.rtl';
		$this->JOB['file_log'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.log';
		$this->JOB['file_stp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.stp';
		if(!empty($this->JOB['outfile'])) $this->JOB['file_buf'] = (preg_match("/^([a-z]:|\/)/", $this->CFG['outfile_path']) ? '' : strtr(dirname(__FILE__), '\\', '/') . '/') . $this->CFG['outfile_path'] . $this->JOB['job'] . '.buf';
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
//		$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'w');


		$fcache = implode('|', array('#SXD20', V_SXD, V_MYSQL, V_PHP, date('Y.m.d H:i:s'), $this->JOB['db'], $this->JOB['charset'], $tabs, $rows, sxd_esc($this->JOB['comment'], false))) . "\n";
		foreach($header AS $t => $o){
			if (!empty($o)) $fcache .= "#{$t} " . implode('|', $o) . "\n";
		}
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'wb');
		$this->fh_log = fopen($this->JOB['file_log'], 'wb');
		$this->rtl = array(time(), time(), $rows, 0, '', '', '', 0, 0, 0, 0, TIMER, "\n");
		$fcache .= "#EOH\n\n";
		$this->fh_tmp = $this->createFile($this->JOB['file_tmp'], $fcache);

		$this->JOB['file'] = sprintf('%s_%s.%s', (isset($this->JOB['title']) ? $this->JOB['job'] : $this->JOB['db']), date('Y-m-d_H-i-s'), $this->JOB['file_ext']);
		$this->JOB['file_name'] = $this->CFG['backup_path'] . $this->JOB['file'];
		$this->JOB['todo'] = $todo;
		$this->saveJob($this->JOB['job'], $this->JOB);

//		$this->write($fcache);
		$this->addLog(sprintf($this->LNG['backup_begin'], $this->JOB['db']));
		$this->runBackupJob();
	}
	protected function runBackupJob($continue = false){
		if($continue){
			$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'a');
			$this->mysql->select_db($this->JOB['db']);
		}
		$this->mysql->query("SET SQL_QUOTE_SHOW_CREATE = 1");
		$types = array('VI' => 'View', 'PR' => 'Procedure', 'FU' => 'Function', 'TR' => 'Trigger', 'EV' => 'Event');
		$fcache = '';
		$writes = 0;
		
		if(V_MYSQL > 40101) $this->mysql->query("SET SESSION character_set_results = '" . ($this->JOB['charset'] ? $this->JOB['charset'] : 'binary') ."'") or sxd_my_error();
		$time_old = time();
		$exit_time = $time_old + $this->CFG['time_web'] - 1;
		$no_cache = V_MYSQL < 40101 ? 'SQL_NO_CACHE ' : '';
		foreach($this->JOB['todo'] AS $t => $o){
			if (empty($this->rtl[4])) $this->rtl[4] = $t;
			elseif ($this->rtl[4] != $t) continue;
			foreach($o AS $n){ 
				if (empty($this->rtl[5])) {
					$this->rtl[5] = $n[1];
					$this->rtl[7] = 0;
					$this->rtl[8] = !empty($n[4]) ? $n[4] : 0;
				}
				elseif ($this->rtl[5] != $n[1]) continue;
				// Делаем бэкап
				switch($n[0]){
					case 'TC':
					case 'TD': 				
					case 'TA':
						$from = '';
						if ($n[0] == 'TC' || $this->rtl[7] == 0){
							// Бэкап структуры таблицы
							$r = $this->mysql->query("SHOW CREATE TABLE `{$n[1]}`") or sxd_my_error();
							$item = $r->fetch_assoc();
						    $fcache .= "#\tTC`{$n[1]}`{$n[2]}\t;\n{$item['Create Table']}\t;\n";
						    $this->addLog(sprintf($this->LNG['backup_TC'], $n[1]));
			            	$this->rtl[7] = 0; 
						    if($n[0] == 'TC' || !$n[4]) break;
						    // Бэкапим данные таблицы
							$fcache .= "#\tTD`{$n[1]}`{$n[2]}\t;\nINSERT INTO `{$n[1]}` VALUES \n";
						}
						else {
							$from = " LIMIT {$this->rtl[7]}, {$this->rtl[8]}";
							$this->addLog(sprintf("{$this->LNG['backup_TC']} {$this->LNG['continue_from']}", $n[1], $this->rtl[7]));
						}
						
						// ADDED 2.0.7 PRO
						// Режим OUTFILE
						if($this->JOB['outfile'] == 1) {
													
							$buffer = $this->CFG['outfile_size'] * 1024 * 1024;
							$limit = 1 + floor($buffer / ($n[5]/$n[4]));
							fwrite($this->fh_tmp, "{$fcache}");
							$fcache = '';
							//$this->addLog($n[4]);
							for($i = 0; $i < $n[4]; $i += $limit){
								if (file_exists($this->JOB['file_buf'])) unlink($this->JOB['file_buf']);
								
								if($i) fwrite($this->fh_tmp, ",\n");
								fwrite($this->fh_tmp, '(');
								//error_log("\nSELECT SQL_NO_CACHE * INTO OUTFILE '" . strtr(dirname(__FILE__), '\\', '/') . "/{$this->CFG['backup_path']}buffer.tmp' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY \"'\" LINES TERMINATED BY '\\r\\n' STARTING BY ',\\\0(' FROM `{$n[1]}`" . ($n[5] < $buffer ? '' : " LIMIT {$i}, {$limit}"), 3, "outfile.log");
								$this->mysql->query("SELECT * INTO OUTFILE '{$this->JOB['file_buf']}' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY \"'\" LINES TERMINATED BY '\\\0\\\0\\\0\\\0' FROM `{$n[1]}`" . ($n[5] < $buffer ? '' : " LIMIT {$i}, {$limit}")) or sxd_my_error();
								
								$fi = fopen($this->JOB['file_buf'], 'r+');
								$z = 0;
								ftruncate($fi, filesize($this->JOB['file_buf']) - 4);
								if(!feof($fi)){
									while($fcache = fread($fi, 61440)){
										if (substr($fcache, -1) == "\0") $fcache .= fread($fi, 3);
										$z = substr_count($fcache, "\0\0\0\0");
										$this->rtl[7]  += $z;  
										$this->rtl[10] += $z;
										$fcache = str_replace(array("\n", "\r", /*"\"", "\32",*/ "\0\0\0\0"), array('\\n', '\\r', /*'\\"', '\\Z',*/ "),\n("), $fcache);
										$this->write($fcache);
									}
								}
								
								fclose($fi);
								fwrite($this->fh_tmp, ')');
								$this->rtl[7]++;  
								$this->rtl[10]++;
							}
							/*$this->rtl[7]  += $n[4];  
							$this->rtl[10] += $n[4];*/
							
							@unlink($this->JOB['file_buf']);
							fwrite($this->fh_tmp, "\t;\n");
						} 
						else {
						///
						
							// Определяем типы полей
							$notNum = array();
							$r = $this->mysql->query("SHOW COLUMNS FROM `{$n[1]}`") or sxd_my_error();
				            $fields = 0;
				            while($col = $r->fetch_array()) {
			            		// TODO:  типы SET, ENUM и BIT
            					$notNum[$fields] = preg_match("/^(tinyint|smallint|mediumint|bigint|int|float|double|real|decimal|numeric|year)/", $col['Type']) ? 0 : 1; 
            					$fields++;
				            }
				            $time_old = time();
				            $z = 0;
				            // Достаем данные
				            $r = $this->mysql->query("SELECT {$no_cache}* FROM `{$n[1]}`{$from}", MYSQLI_USE_RESULT);
				            while($row = $r->fetch_row()) {
			            		if (strlen($fcache) >= 61440) {
			            	 		$z = 0;
									if($time_old < time()) {
										if(file_exists($this->JOB['file_stp'])){
											$type = file_get_contents($this->JOB['file_stp']);
											$this->rtl[9] = !empty($type) ? $type : 2;
											$this->write($fcache);
											if($type == 1) {
												
											}
											unset($this->rtl);
											exit;
										}
										$time_old = time();
										if($time_old >= $exit_time){
											$this->rtl[9] = 3;
											$this->write($fcache);
											unset($this->rtl);
											exit;
										}
										clearstatcache(); 
									}
									$this->write($fcache); 
								}
								for($k = 0; $k < $fields; $k++){
									if(!isset($row[$k])) {$row[$k] = 'NULL';}
									elseif($notNum[$k]) {$row[$k] =  '\'' . $this->mysql->escape_string($row[$k]) . '\'';}
								}
								$fcache .= '(' . implode(',', $row) . "),\n";
								$this->rtl[7]++;  
								$this->rtl[10]++;
							}
							unset($row);
							$r->free();
							$fcache = substr_replace($fcache, "\t;\n",  -2, 2);
						}
					break;

					/*case 'VI':
					case 'PR': 
					case 'FU':
					case 'EV':
					case 'TR':*/
					default:
						if(V_MYSQL < 50121 && $n[0] == 'TR'){
							// SHOW CREATE TRIGGER отсутствует до MySQL 5.1.21
							$r = $this->mysql->query("SELECT * FROM `INFORMATION_SCHEMA`.`TRIGGERS` WHERE `TRIGGER_SCHEMA` = '{$this->JOB['db']}' AND `TRIGGER_NAME` = '{$n[1]}'") or sxd_my_error();
							$item = $r->fetch_assoc();
							$fcache .= "#\tTR`{$n[1]}`{$n[2]}\t;\nCREATE TRIGGER `{$item['TRIGGER_NAME']}` {$item['ACTION_TIMING']} {$item['EVENT_MANIPULATION']} ON `{$item['EVENT_OBJECT_TABLE']}` FOR EACH ROW {$item['ACTION_STATEMENT']}\t;\n";
						}
						else {
							$this->addLog(sprintf($this->LNG['backup_' . $n[0]], $n[1]));
							$r = $this->mysql->query("SHOW CREATE {$types[$n[0]]} `{$n[1]}`") or sxd_my_error();
							$item = $r->fetch_assoc();
							$fcache .= "#\t{$n[0]}`{$n[1]}`{$n[2]}\t;\n" . preg_replace("/DEFINER=`.+?`@`.+?` /", '', ($n[0] == 'TR' ? $item['SQL Original Statement'] : $item['Create ' . $types[$n[0]]])) . "\t;\n";
						}
				}
				
				$this->rtl[5] = '';
			}
			$this->rtl[4] = '';
		}
		
		$this->rtl[5] = round(microtime(1) - $this->rtl[11], 4);
		$this->rtl[6] = '';
		$this->rtl[7] = 0;
		$this->rtl[8] = 0;
		$this->write($fcache);
		fclose($this->fh_tmp);
		rename($this->JOB['file_tmp'], $this->JOB['file_name']);
		$this->addLog(sprintf($this->LNG['backup_end'], $this->JOB['db']));
		if(file_exists('sxd2ftp.php')) include('sxd2ftp.php');
		if ($this->JOB['del_time'] || $this->JOB['del_count']) {
            $this->addLog($this->LNG['autodelete']);
            $deldate = '';
            if (!empty($this->JOB['del_time'])){ // Удаление по дням
                $deldate = date("Y-m-d_H-i-s", time() - intval($this->JOB['del_time']) * 86400);
            }
            $deleted = false;
            if ($dh = opendir($this->CFG['backup_path'])) {
                $files = array();
                $name = isset($this->JOB['title']) ? $this->JOB['job'] : $this->JOB['db'];
                while (false !== ($file = readdir($dh))) { 
                    if (preg_match("/^{$name}_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql/", $file, $m)) { 
                        if ($deldate && $m[1] < $deldate) {
                            if(unlink($this->CFG['backup_path'] . $file)) $this->addLog(sprintf($this->LNG['del_by_date'], $file));
                            else  $this->addLog(sprintf($this->LNG['del_fail'], $file));
                            $deleted = true;
                        }
                        else {$files[$m[1]] = $file;}
                    }
                }
                closedir($dh);
                // Сортируем файлы по дате и удаляем самые старые
                if (!empty($this->JOB['del_count'])){
                    ksort($files);
                    $file_to_delete = count($files) - $this->JOB['del_count'];
                    foreach ($files AS $file){
                        if ($file_to_delete-- > 0){ 
                        	if(unlink($this->CFG['backup_path'] . $file)) $this->addLog(sprintf($this->LNG['del_by_count'], $file));
                            else  $this->addLog(sprintf($this->LNG['del_fail'], $file));
                            $deleted = true;
                        }
                    }
                }
            }
            if(!$deleted) $this->addLog($this->LNG['del_nothing']);
		}
		fclose($this->fh_log);
		$this->rtl[4] = 'EOJ';
		fseek($this->fh_rtl, 0);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		fclose($this->fh_rtl);
		unset($this->rtl);
	}
	protected function setNames($collation){
		if(empty($collation)) return;
		if($this->rtl[6] != $collation) {
			$query = $this->query;
			$query('SET NAMES \'' . preg_replace('/^(\w+?)_/', '\\1\' COLLATE \'\\1_', $collation) . '\''); // TODO:  переключение кодировок
			if(!$this->rtl[7]) $this->addLog(sprintf($this->LNG['set_names'], $collation));
			$this->rtl[6] = $collation;	
		}
	}
	protected function write(&$str){
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		$this->rtl[3] += fwrite($this->fh_tmp, $str);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$str = '';
	}
	protected function addLog($str, $type = 1){
		fwrite($this->fh_log, date('Y.m.d H:i:s') . "\t{$type}\t{$str}\n");
	}
	protected function getDBList(){
		$dbs = $items = array();
        if (!V_MYSQL) return $dbs; 
        $qq = (V_MYSQL < 50000) ? '' : '\'';
		if ($this->CFG['my_db']) {
			$tmp = explode(',', $this->CFG['my_db']);
			foreach($tmp AS $d){
				$d = trim($d);
				$items[] = $qq . sxd_esc($d, false) . $qq;
				$dbs[$d] = "{$d} (0)";
			}
		}
		else{
			$r = $this->mysql->query("SHOW DATABASES") or sxd_my_error();
    		while($item = $r->fetch_row()){
    			if($item[0] == 'information_schema' || $item[0] == 'mysql' || $item[0] == 'performance_schema') continue;
    			$items[] = $qq . sxd_esc($item[0], false) . $qq;
    			$dbs[$item[0]] = "{$item[0]} (0)";
    		}	
		}
		if(V_MYSQL < 50000){
			foreach($items AS $item){
    			$tables = $this->mysql->query("SHOW TABLES FROM `{$item}`") or sxd_my_error();
    			if ($tables) {
    	  			$tabs = $tables->num_rows;
    	  			$dbs[$item] = "{$item} ({$tabs})";
    	  		}
			}
		}
		else {
			$where = (count($items) > 0) ? 'WHERE `table_schema` IN (' . implode(',', $items) . ')' : '';
			$r = $this->mysql->query("SELECT `table_schema`, COUNT(*) FROM `information_schema`.`tables` {$where} GROUP BY `table_schema`") or sxd_my_error();
			while($item = $r->fetch_row()){
    			if($item[0] == 'information_schema' || $item[0] == 'mysql' || $item[0] == 'performance_schema') continue;
    			$dbs[$item[0]] = "{$item[0]} ({$item[1]})";
    		}
		}
	    return $dbs;
	}
	protected function getCharsetList(){
		$tmp = array(0 => '- auto -');
		if (!V_MYSQL) return $tmp; 
		if(V_MYSQL > 40101) {
			$def_charsets = '';
			if(!empty($this->CFG['charsets'])){
				$def_charsets = preg_match_all("/([\w*?]+)\s*/", $this->CFG['charsets'], $m, PREG_PATTERN_ORDER) ? '/^(' . str_replace(array('?','*'), array('.','\w+?'), implode('|', $m[1])) . ')$/i' : '';
			}
    		$r = $this->mysql->query("SHOW CHARACTER SET") or sxd_my_error();
    		if ($r) {
    			while($item = $r->fetch_assoc()){
    	  			if (empty($def_charsets) || preg_match($def_charsets, $item['Charset'])) $tmp[$item['Charset']] = "{$item['Charset']}"; // ({$item['Description']})
    			}
			}
		}
	    return $tmp;
	}
	protected function getCollationList(){
		$tmp = array(); 
		if (!V_MYSQL) return $tmp; 
		if(V_MYSQL > 40101) {
			$def_charsets = '';
			if(!empty($this->CFG['charsets'])){
				$def_charsets = preg_match_all("/([\w*?]+)\s*/", $this->CFG['charsets'], $m, PREG_PATTERN_ORDER) ? '/^(' . str_replace(array('?','*'), array('.','\w+?'), implode('|', $m[1])) . ')$/i' : '';
			}
    		$r = $this->mysql->query("SHOW COLLATION") or sxd_my_error();
    		if ($r) {
    			while($item = $r->fetch_assoc()){
    	  			if (empty($def_charsets) || preg_match($def_charsets, $item['Charset'])) $tmp[$item['Charset']][$item['Collation']] = $item['Default'] == 'Yes' ? 1 : 0; 
    			}
			}
		}
	    return $tmp;
	}
	protected function getObjects($tree, $db_name){
		$this->mysql->select_db($db_name);
		// Достаем таблицы
		$r = $this->mysql->query('SHOW TABLE STATUS');
		$tab_prefix_last = $tab_prefix = '*';
		$objects = array('TA' => array(), 'VI' => array(), 'PR' => array(), 'FU' => array(), 'TR' => array(), 'EV' => array());
		if($r){
			while($item = $r->fetch_assoc()){
				if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) {
					$objects['VI'][]= $item['Name'];
				}
				else{
					$objects['TA'][] = array($item['Name'], $item['Rows'], $item['Data_length']);
				} 
			}
			
			if (V_MYSQL > 50014 && $tree != 'services') {
				$shows = array(
					"PROCEDURE STATUS WHERE db='{$db_name}'", 
					"FUNCTION STATUS WHERE db='{$db_name}'",
					'TRIGGERS'
				);
				if(V_MYSQL > 50100) $shows[] = "EVENTS WHERE db='{$db_name}'";
				// TODO: проверка событий и триггеров
				for($i = 0, $l = count($shows); $i < $l; $i++){
					$r = $this->mysql->query('SHOW ' . $shows[$i]);
					if($r && $r->num_rows > 0) {
						$col_name = $shows[$i] == 'TRIGGERS' ? 'Trigger' : 'Name';
						$type = substr($shows[$i], 0, 2);
						while($item = $r->fetch_assoc()){
							$objects[$type][] = $item[$col_name];	
						}
					}
				}
			}
			else {
				$objects['VI'] = array();
			}
		}
		return $this->formatTree($tree, $objects);
	}
	protected function getFileObjects($tree, $name, $formatTree = true){
		// Достаем таблицы
		$objects = array('TA' => array(), 'VI' => array(), 'PR' => array(), 'FU' => array(), 'TR' => array(), 'EV' => array());
		if(!preg_match('/\.sql(\.(gz|bz2))?$/i', $name, $m)) return '';
		$name = $this->CFG['backup_path'] . $name;
		if(!is_readable($name)) {return "sxd.tree.{$tree}.error(sxd.lng('err_fopen'))";}
		$fp   = $this->openFile($name, 'r');
		/*if(!empty($m[2])) {
            if (strtolower($m[2]) == 'gz') {
                stream_filter_append($fp, 'zlib.inflate', STREAM_FILTER_READ);
            } elseif (strtolower($m[2]) == 'bz2') {
                stream_filter_append($fp, 'bzip2.decompress', STREAM_FILTER_READ);
            }
        }*/
		$temp = fread($fp, 60000);



		// Формат файла Sypex Dumper 2 - SXD20
		//if(!preg_match('/^(#SXD20\|.+?)\n#EOH\n/s', $temp, $m)) return "sxd.tree.{$tree}.error(sxd.lng('err_sxd2'));z('restore_savejob').disabled = z('restore_runjob').disabled = true;";
		if(preg_match('/^(#SXD20\|.+?)\n#EOH\n/s', $temp, $m)){
			$head = explode("\n", $m[1]);
			$h = explode('|', $head[0]);
//			echo "/*" . var_export($h,1) . "*/";
			for($i = 1, $c = count($head); $i < $c; $i++){
				$objects[substr($head[$i], 1,2)] = explode('|', substr($head[$i], 4));
			}
			for($i = 0, $l = count($objects['TA']); $i < $l; $i++){
				$objects['TA'][$i] = explode('`', $objects['TA'][$i]);
			}
		}
		else {
			$h[9] = '';
		}
//		echo "/*" . var_export($objects,1) . "*/";
		return $formatTree ? $this->formatTree($tree, $objects) .  "sxd.comment.restore.value = '{$h[9]}';z('restore_savejob').disabled = z('restore_runjob').disabled = false;" : $objects;
	}
	protected function formatTree($tree, &$objects){
		$obj = '';
		$pid = $row = 1;
		$info = array(
			'TA' => array($this->LNG['obj_tables'], 1),
			'VI' => array($this->LNG['obj_views'], 3), 
			'PR' => array($this->LNG['obj_procs'], 5), 
			'FU' => array($this->LNG['obj_funcs'], 7), 
			'TR' => array($this->LNG['obj_trigs'], 9), 
			'EV' => array($this->LNG['obj_events'], 11)
		);
		// Находим таблицы с префиксами
		$tab_prefix_last = $tab_prefix = '*';
		for($i = 0, $l = count($objects['TA']); $i < $l; $i++){
			$t = $objects['TA'][$i];
			$tab_prefix = preg_match("/^([a-z0-9]+_)/", $t[0], $m) ? $m[1] : '*';
			if ($tab_prefix != $tab_prefix_last) {
				if ($tab_prefix != '*') $objects['TA']['*'][] = $tab_prefix;
				$tab_prefix_last = $tab_prefix;
			}
			$objects['TA'][$tab_prefix][] = $t;
			unset($objects['TA'][$i]);
		}
		foreach($objects AS $type => $o){
			if(!count($o)) continue;
			if($type == 'TA') {
				$open_childs = count($o['*']) > 1 ? 0 : 1;
				$obj .= "[{$row},0," . sxd_esc($info[$type][0]) . ",1,1,1],";
				$row++;
				foreach($o['*'] AS $value){
					if(is_string($value)){
						if(count($o[$value]) > 1)	{
							$obj .= "[{$row},1,'{$value}*',1,1,{$open_childs}],";	
							$pid = $row++; 
							for($i = 0, $l = count($o[$value]); $i < $l; $i++){
								$checked = ($o[$value][$i][1] == '' && $o[$value][$i][2] == '') ? 2 : 1;
								$obj .= "[{$row},{$pid}," . sxd_esc($o[$value][$i][0]) . ",2,{$checked},{$o[$value][$i][2]}],";
								$row++;
							}
						}
						else {
							$value = $o[$value][0];	
						}
					}
					//$pid = 1;
					if (is_array($value)){
						$checked = ($value[1] == '' && $value[2] == '') ? 2 : 1;
						$obj .= "[{$row},1,'{$value[0]}',2,{$checked},{$value[2]}],";
						$row++;	
					}
				}
			}
			else {
				$obj .= "[{$row},0," . sxd_esc($info[$type][0]) . ",{$info[$type][1]},1,1],";
				$pid = $row++;
				$info[$type][1]++;
				for($i = 0, $l = count($o); $i < $l; $i++){
					$o[$i] = sxd_esc($o[$i], false);
					$obj .= "[{$row},{$pid},'{$o[$i]}',{$info[$type][1]},1,0],";
					$row++;	
				}
			} 
		}
		$add = '';
		if($tree == 'restore') $add = "z('autoinc').disabled = z('prefix').disabled = z('restore_type').disabled = z('prefix_from').disabled = z('prefix_to').disabled = z('savesql').disabled = " . ($obj ? 'false' : 'true') . ";";
		return ($obj ? 'sxd.tree.' . $tree . '.drawTree([' . substr_replace($obj, ']',  -1) . ");" : "sxd.tree.{$tree}.error(sxd.lng('err_sxd2'));") . $add;	
	}
	protected function getFileList(){
		$files = array();
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^.+?\.sql(\.(gz|bz2))?$/", $file)) {
                    $files[$file] = $file;
                }
            }
            closedir($handle);
        }
        ksort($files);
		return $files;
	}
	protected function getSavedJobs(){
		$sj = array('sj_backup' => array(), 'sj_restore' => array(),);
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^sj_(.+?)\.job.php$/", $file)) {
                	include($this->CFG['backup_path'] . $file);
                    $sj['sj_' . $JOB['type']][$JOB['job']] = "<b>{$JOB['job']}</b><br><i>{$JOB['title']}&nbsp;</i>";
                }
            }
            closedir($handle);
        }
        if(count($sj['sj_backup']) > 0){
        	ksort($sj['sj_backup']);	
		}
        else {
        	$sj['sj_backup'] = array(0 => '<b>No Saved Jobs</b><br>' . $this->LNG['no_saved']);	
		}
		if(count($sj['sj_restore']) > 0){
        	ksort($sj['sj_restore']);	
		}
        else {
        	$sj['sj_restore'] = array(0 => '<b>No Saved Jobs</b><br>' . $this->LNG['no_saved']);	
		}
		return "sxd.clearOpt('sj_backup');sxd.clearOpt('sj_restore');sxd.addOpt(" . sxd_php2json($sj) . ");";
	}
	protected function getFileListExtended(){
		$files = array();
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^.+?\.sql(\.(gz|bz2))?$/", $file, $m)) {
                    $fp   = $this->openFile($this->CFG['backup_path'] . $file, 'r');
                    $ext  = !empty($m[2]) ? $m[2] : 'sql';
                    $temp = fgets($fp);
                    if(preg_match('/^(#SXD20\|.+?)\n/s', $temp, $m)){
                    	$h = explode('|', $m[1]);
                    	$files[] = array($h[5], substr($h[4], 0, -3), $ext, $h[7], number_format($h[8], 0, '', ' '), filesize($this->CFG['backup_path'] . $file), $h[9], $file);	
					}
					elseif(preg_match('/^(#SKD101\|.+?)\n/s', $temp, $m)){ // ADDED 2.0.7 PRO
                    	$h = explode('|', $m[1]);
                    	$files[] = array($h[1], substr($h[3], 0, -3), $ext, $h[2], number_format($h[4], 0, '', ' '), filesize($this->CFG['backup_path'] . $file), 'SXD 1.0.x', $file);	
					}
					else { // ADDED 2.0.7 PRO
						$files[] = array($file, '-', $ext, '-', '-', filesize($this->CFG['backup_path'] . $file), '', $file);
					}
                }
            }
            closedir($handle);
        }
        function s($a, $b){
		    return strcmp($b[1], $a[1]);
		}
        usort($files, 's');
		return 'sxd.files.clear();sxd.files.add(' . sxd_php2json($files) . ');';
	}
	protected function saveJob($job, $config){
		file_put_contents($this->CFG['backup_path'] . $job . '.job.php', "<?php\n\$JOB = " . var_export($config, true) . ";");
	}
	protected function openFile($name, $mode){
		if($mode == 'r') {
			if(preg_match('/\.(sql|sql\.bz2|sql\.gz|sxe)$/i', $name, $m)) $this->JOB['file_ext'] = strtolower($m[1]); // SXD3
		}
		else{
			switch($this->JOB['zip']) {
				case 0 : $this->JOB['file_ext'] = 'sql'; break;
				case 10: $this->JOB['file_ext'] = 'sql.bz2'; break;
				default: $this->JOB['file_ext'] = 'sql.gz'; break; 
			}
		}
		switch ($this->JOB['file_ext']){
			case 'sql':
				return fopen($name, "{$mode}b");
				break;
			case 'sql.bz2':
				return bzopen($name, $mode);
				break;
			case 'sql.gz':
                $gzhead  = file_get_contents($name, false, null, 0, 2);
                if($gzhead === "\x1f\x8b") {
                    return gzopen($name, $mode . ($mode == 'w' ? $this->JOB['zip'] : ''));
                }
                else{
                    $fh = fopen($name, "{$mode}b");
                    stream_filter_append($fh, 'zlib.inflate', STREAM_FILTER_READ);
                    return $fh;
                }

				break;
			default: return false;
		}
	}

	// ADDED 3.0 PRO

	protected function createFile($name, $info){
//		var_dump($this->JOB);


		if($this->JOB['zip'] > 0 && $this->JOB['zip'] < 10){
			$this->JOB['file_ext'] = 'sql.gz';
            $fh = gzopen($name, "wb");
//			stream_filter_append($fh, 'zlib.deflate', STREAM_FILTER_WRITE, array('level' => $this->JOB['zip'], 'memory' => 9));
		}
		elseif($this->JOB['zip'] == 10){
			$this->JOB['file_ext'] = 'sql.bz2';
            $fh = bzopen($name, "w");
//			stream_filter_append($fh, 'bzip2.compress', STREAM_FILTER_WRITE, array('blocks' => 9, ' work' => 0));
		}
		else {
            $fh = fopen($name, "wb"); // Открыли файл
            $this->JOB['file_ext'] = 'sql';
        }
		/*if(isset($content_key)) {
			$this->JOB['file_ext'] = 'sxe';
			$enc_filter = stream_filter_append($fh, "sxe.encrypt", STREAM_FILTER_WRITE, array('cipher' => $chipher, 'iv' => $content_iv, 'key' => $content_key));
		}*/

        $this->rtl[3] += fwrite($fh, $info);

		return $fh;
	}
	protected function getRandomBytes($length){
		if (function_exists('random_bytes')) {
			return random_bytes($length);
		}
		if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		}
		if (function_exists('mcrypt_create_iv')) {
			return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
		}
		return false;
	}

	protected function PBKDF2($algo, $pass, $salt, $rounds, $len = 0, $raw = true){
		$rounds = $rounds > 10 && $rounds < 50 ? pow(2, $rounds) : $rounds;
		if(function_exists('hash_pbkdf2')){
			return hash_pbkdf2($algo, $pass, $salt, $rounds, $len, $raw);
		}
		else {
			$hlen = strlen(hash($algo, 0, 1));
			$blocks = ceil($len / $hlen);
			if ($len == 0) $len = $hlen;
			$out = '';
			for ($i = 1; $i <= $blocks; $i++) {
				$last = $salt . pack("N", $i);
				$xor = $last = hash_hmac($algo, $last, $pass, 1);
				for ($j = 1; $j < $rounds; $j++) {
					$xor ^= ($last = hash_hmac($algo, $last, $pass, 1));
				}
				$out .= $xor;
			}
		}
		return $raw ? substr($out, 0, $len) : bin2hex(substr($out, 0, $len));
	}
	/// ADDED 3.0 PRO
	
	// ADDED 2.0.7 PRO
	protected function skipper($curtype, $curobj = 'null'){
		static $curpos  = -1;
		$founded = ($curobj == 'null') ? true : false;
		$skip    = false;
		if($curpos >= 0 && $curobj == $this->JOB['todo'][$curpos][1]) {
//			error_log("--------------------------------------\nНайдена таблица {$curobj} skipper = false\n", 3, "curpos.log");
			if($curtype == $this->JOB['todo'][$curpos][0] || ($curtype == 'TC' && $this->JOB['todo'][$curpos][0] == 'TA' || $curtype == 'TD' && $this->JOB['todo'][$curpos][0] == 'TA'))
				return false;
			elseif ($curtype == 'TD' && $this->JOB['todo'][$curpos][0] == 'TC') {$founded = true; $skip = true;}
		}
		
//		error_log("--------------------------------------\n", 3, "curpos.log");
		for($curpos++, $l = count($this->JOB['todo']); $curpos < $l; $curpos++){
			$obj = $this->JOB['todo'][$curpos];
			if (!$founded && $obj[1] == $curobj && ($obj[0] == $curtype || ($obj[0] == 'TA' && ($curtype == 'TC' || $curtype == 'TD')))) $founded = true;
			
//			error_log("{$curpos}\t{$curtype}\t{$curobj}\t{$obj[1]}\t{$obj[2]}\t{$founded}\n", 3, "curpos.log");
			
			if ($skip && $obj[2] != 'SKIP') {
				
//				error_log("------------->\t" . strtr($obj[0], 'A', 'C') . "`{$obj[1]}------------------\n", 3, "curpos.log");
				return "\t" . strtr($obj[0], 'A', 'C') . "`{$obj[1]}";
			}
			elseif ($founded) {
				 if($obj[2] != 'SKIP') return false;
				 else $skip = true;
			}
			//else
		
		}
//		error_log("-------------END OF JOB------------------\n", 3, "curpos.log");
		return 'EOJ';
	}
	///

	public function verToInt($ver) {
		return preg_match("/^(\d+)\.(\d+)\.(\d+)/", $ver, $m) ? (int)sprintf("%d%02d%02d", $m[1], $m[2], $m[3]) : 0;
	}

}


function sxd_read_sql($f, &$seek, $ei, $skipto = false){
	static $l = '';
	static $r = 0;
	if($skipto == 'EOJ') return false;
	$fs = ftell($f);
	while($r || $s = fread($f, 61440)){
		if(!$r) $l .= $s;

		if($skipto) {
			$pos = strpos($l, $skipto);
			if ($pos === false){
				$l = substr($l, -strlen($skipto));
				$seek = strlen($l);
				$r = 0;
				continue;
			}
			else {
				$l = substr($l, $pos-1);
			}
		} 
		$pos = strpos($l, "\t;\n");
		if ($pos !== false) {
			// Есть окончание запроса
			$q = substr($l, 0, $pos);
			$l = substr($l, $pos + 3);
			$r = 1;
			$seek = strlen($l);
			return $q;
		}
		if($ei) {
			$pos = strrpos($l, "\n");
			if($pos !== false && $l[$pos-1] === ',') {
				// Окончание не найдено
				$q = substr($l, 0, $pos-1);
				$l = substr($l, $pos+1);
				$seek = strlen($l);
				$r = 0;
				return $q;
			}
		}
		$r = 0;	
	}
	if (!empty($l)) {
		return $l;
	}
	return false;
}
function sxd_read_foreign_sql($f, &$seek, $ei, $delimiter = ";", $eol = "\n"){
	static $l = '';
	static $r = 0;
	$fs = ftell($f);
	$delim_len = strlen($delimiter . $eol);
	while($r || $s = fread($f, 61440)){
		if(!$r) $l .= $s;
		$pos = strpos($l, $delimiter . $eol);
		if ($pos !== false) {
			// Есть окончание запроса
			$q = substr($l, 0, $pos);
			$l = substr($l, $pos + $delim_len);
			$r = 1;
			$seek = strlen($l);
			return $q;
		}
		if($ei) {
			$pos = strrpos($l, $eol);
			if($pos > 0 && $l[$pos-1] === ',') {
				// Окончание не найдено
				$q = substr($l, 0, $pos-1);
				$l = substr($l, $pos + strlen($eol));
				$seek = strlen($l);
				$r = 0;
				return $q;
			}
		}
		$r = 0;	
	}
	$r = 0;
	if (!empty($l)) {
		return $l;
	}
	return false;
}
function sxd_check($n, $obj, $filt){
	return isset($obj[$n]) || ($filt && preg_match($filt, $n));
}
function sxd_php2json($obj){
	if(count($obj) == 0) return '[]';
	$is_obj = isset($obj[0]) && isset($obj[count($obj) - 1]) ? false : true;
	$str = $is_obj ? '{' : '[';
    foreach ($obj AS $key  => $value) {
    	$str .= $is_obj ? "'" . addcslashes($key, "\n\r\t'\\/") . "'" . ':' : ''; 
        if     (is_array($value))   $str .= sxd_php2json($value);
        elseif (is_null($value))    $str .= 'null';
        elseif (is_bool($value))    $str .= $value ? 'true' : 'false';
		elseif (is_numeric($value)) $str .= $value;
		else                        $str .= "'" . addcslashes($value, "\n\r\t'\\/") . "'";
		$str .= ',';
    }
	return  substr_replace($str, $is_obj ? '}' : ']', -1);
}
function sxd_ver2int($ver){
	return preg_match("/^(\d+)\.(\d+)\.(\d+)/", $ver, $m) ? sprintf("%d%02d%02d", $m[1], $m[2], $m[3]) : 0;
}
function sxd_error_handler($errno, $errmsg, $filename, $linenum, $vars){
    global $SXD;
    if(SXD_DEBUG) error_log(date('Y-M-d H:i:s') . "\t[index.php]\t{$errmsg}\n\n", 3, "backup/error.log");
    if($SXD->try) return;
	if($errno == 8192) return;
    if(strpos($errmsg, 'timezone settings')) return;
    if(strpos($errmsg, 'ob_end_flush')) return;
    $errortype = array(1 => 'Error', 2 => 'Warning', 4 => 'Parsing Error', 8 => 'Notice', 16 => 'Core Error', 32 => 'Core Warning', 64 => 'Compile Error',
					   128 => 'Compile Warning', 256 => 'MySQL Error', 512 => 'Warning', 1024 => 'Notice',
						2048 => 'Strict', 8192 => 'Deprecated', 16384 => 'Deprecated');
	$str = sxd_esc("{$errortype[$errno]}: {$errmsg} ({$filename}:{$linenum})", false);
	//if(SXD_DEBUG) error_log(date(r) . "\n[index.php]\n{$str}\n", 3, "backup/error.log");
	
    if($errno == 8 || $errno == 1024) {
    	if (!$SXD->fh_log && !$SXD->fh_rtl) echo isset($_POST['ajax']) ? "alert('" . ($str) . "');" : $str;
    	else {
    		fwrite($SXD->fh_log, date('Y.m.d H:i:s') . "\t3\t{$str}\n");
		}
	}
    elseif($errno < 1024) {
    	$SXD->error = true;
    	if (!$SXD->fh_log && !$SXD->fh_rtl) echo isset($_POST['ajax']) ? "alert('" . ($str) . "');" : $str;
    	else {
    		$SXD->rtl[1] = time();
    		$SXD->rtl[9] = 5;
    		fseek($SXD->fh_rtl, 0);
			fwrite($SXD->fh_rtl, implode("\t", $SXD->rtl));
    		fwrite($SXD->fh_log, date('Y.m.d H:i:s') . "\t4\t{$str}\n");
    		unset($SXD->rtl);
		}
		
    	die;
	}
}
function sxd_esc($str, $quoted = true){
	return $quoted ? "'" . addcslashes($str, "\\\0\n\r\t\'") . "'" : addcslashes($str, "\\\0\n\r\t\'");
}
function sxd_my_error(){
global $SXD;
	trigger_error($SXD->mysql->error, E_USER_ERROR);// TODO: обработка ошибок
}
function l(){
    $temp = '';
    if(file_exists('sxd.lic.php')) include('sxd.lic.php'); else return false;
    $chars = '8qQpro+ABfT4aXOK7kb5u3Sm=VWJLw6E1GY0xDteyl/ZPid2M9NzcnCRFIUhHgsvj';
    $enc = substr($key, 0, 10) . substr($key, -10);
    $len = strlen($chars);
	for ($i = 10, $l = strlen($key)-10; $i < $l; $i++){
		$k = strpos($chars, $key[$i]);
		$z = ord($enc[($i-10)%20])%$len;
		$temp .= $chars[(($len + $k - $z) % $len)];
	}
	$temp = I($temp);
	$crc = unpack('Ni', substr($temp, -4));
	$pack = substr($temp, 0, -4);
	if(1 || in_array($crc['i'], c($pack))){// TODO: crc32
		$len = unpack('Cint', substr($temp, 0, 1));
		return unpack("Chl/Nc/Ne/A{$len['int']}h", $pack);
	}
	else {
		return false;
	}
}
function c($s){
	$m = 2147483648;
	$c = crc32($s);
	if($c > $m - 1) {
	  return array($c, $c - 2 * $m);
	}
	return array($c);
}
function I($s){$I = "base64_decode"; return $I($s);}
function sxd_shutdown(){
	global $SXD;
	if(isset($SXD->fh_rtl) && is_resource($SXD->fh_rtl) && !empty($SXD->rtl) && empty($SXD->error)) {
		$SXD->rtl[1] = time();
		if(!empty($SXD->JOB['file_stp']) && file_exists(dirname(__FILE__) . '/' . $SXD->JOB['file_stp'])){
			$type = file_get_contents(dirname(__FILE__) . '/' . $SXD->JOB['file_stp']);
			$SXD->rtl[9] = !empty($type) ? $type : 2;
		}
		else $SXD->rtl[9] = 5;
		fseek($SXD->fh_rtl, 0);
		fwrite($SXD->fh_rtl, implode("\t", $SXD->rtl));
	}
}

