<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const WHOIS_NAVI = ['htitle' => _WHOIS_LIC, 'best_href' => '', 'pop_href' => '', 'liste_href' => ''];


function mwhois(): void {
	global $db, $afile, $user, $conf, $home, $locale, $tpl;
	global $domainwhois, $ext, $nomatch, $server, $domainopt;
	$domainlicens = getVar('req', 'domain_licens', 'word');
	
	$licensopt = $tpl->getHtmlPart('fieldset-post-form', [
		'action'        => 'index.php?name='.$conf['name'],
		'legend'        => _WHOIS_LICENS,
		'primary_field' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'domain_licens', 'value_attr' => $domainlicens]),
		'hidden_fields' => [
			['name_attr' => 'option', 'value_attr' => 'licens', 'input_attr' => ''],
		],
		'submit_label'  => _WHOIS_PR,
		'is_danger'     => true,
	]);
	
	$domainwhois = getVar('req', 'domain_whois', 'word');
	$ext = getVar('req', 'ext', 'word');
	
	$domainoptOptions = '';
	
	$exmas = ['ru', 'com', 'net', 'org', 'biz', 'info', 'name', 'us', 'de', 'in', 'co.in', 'firm.in', 'gen.in', 'ind.in', 'net.in', 'org.in', 'com.ru', 'net.ru', 'org.ru', 'pp.ru', 'spb.ru', 'msk.ru', 'ws', 'cn'];
	foreach ($exmas as $val) {
		if ($val != '') {
			$domainoptOptions .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$val, 'label_text' => (string)'.'.$val, 'is_selected' => $val == $ext]);
		}
	}
	
	$domainopt = $tpl->getHtmlPart('fieldset-post-form', [
		'action'          => 'index.php?name='.$conf['name'],
		'legend'          => _WHOIS_DOM,
		'primary_field'   => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'domain_whois', 'value_attr' => $domainwhois]),
		'secondary_field' => $tpl->getHtmlFrag('select', ['name_attr' => 'ext', 'options_html' => $domainoptOptions]),
		'hidden_fields'   => [
			['name_attr' => 'option', 'value_attr' => 'check', 'input_attr' => ''],
		],
		'submit_label'    => _WHOIS_PR,
		'is_success'      => true,
	]);

	$serverdefs = [
	'ru' => ['whois.tcinet.ru','No entries found'],
	'com' => ['whois.verisign-grs.com','No match for'],
	'net' => ['whois.verisign-grs.com','No match for'],
	'org' => ['whois.publicinterestregistry.org','NOT FOUND'],
	'biz' => ['whois.nic.biz','Not found'],
	'info' => ['whois.nic.info','NOT FOUND'],
	'name' => ['whois.nic.name','No match'],
	'us' => ['whois.nic.us','Not found:'],
	'de' => ['whois.denic.de','Status: free'],
	'in' => ['whois.nixiregistry.in','NOT FOUND'],
	'co.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'firm.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'gen.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'ind.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'net.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'org.in' => ['whois.nixiregistry.in','NOT FOUND'],
	'com.ru' => ['whois.ripn.net','No entries found'],
	'net.ru' => ['whois.ripn.net','No entries found'],
	'org.ru' => ['whois.ripn.net','No entries found'],
	'pp.ru' => ['whois.ripn.net','No entries found'],
	'spb.ru' => ['whois.ripn.net','No entries found'],
	'msk.ru' => ['whois.ripn.net','No entries found'],
	'ws' => ['whois.nic.ws','No match for'],
	'cn' => ['whois.cnnic.net.cn','No entries']];
	
	$option = getVar('req', 'option', 'var');
	setHead(['title' => _WHOIS_LIC]);
	$cont = getModuleNavi(['title' => _WHOIS_LIC] + WHOIS_NAVI);
	$cont .= $licensopt;
	if ($option == 'licens' && !namecheck($domainlicens)) {
		$result = $db->getSqlQuery('SELECT website FROM '.PREFIX_DB.'_clients WHERE status != \'2\'');
		$list = [];
		while ([$website] = $db->getSqlRow($result)) $list[] = $website;
		$list = implode(',', $list);
		$mass = explode(',', $list);
		$licens = false;
		foreach ($mass as $val) {
			if ($val != '' && ($val == 'http://'.strtolower($domainlicens) || $val == 'http://www.'.strtolower($domainlicens))) {
				$licens = true;
				break;
			}
		}
		$result = '';
		if ($licens) {
			$result .= $tpl->getHtmlFrag('span', ['text' => _DOMAIN.' «'.$domainlicens.'» '._WHOIS_ISL.'!', 'is_success' => true]);
		} else {
			$result .= $tpl->getHtmlFrag('span', ['text' => _DOMAIN.' «'.$domainlicens.'» '._WHOIS_NOL.'!', 'is_danger' => true]);
			$result .= ((is_user() && $conf['whois']['add'] == 1) || (!is_user() && $conf['whois']['addquest'] == 1)) ? $tpl->getHtmlPart('mini-post-form', [
				'action'        => 'index.php?name='.$conf['name'],
				'hidden_fields' => [
					['name_attr' => 'op', 'value_attr' => 'add', 'input_attr' => ''],
					['name_attr' => 'domain', 'value_attr' => $domainlicens, 'input_attr' => ''],
				],
				'submit_label'  => _WHOIS_LICENS_SEND,
			]) : '';
		}
		$cont .= $tpl->getHtmlPart('fieldset-block', ['legend' => _WHOIS_SUCH, 'content' => $result, 'is_center' => true, 'is_info' => true]);
	} elseif ($option == 'licens') {
		$cont .= printresults(namecheck($domainlicens), 1);
	}
	if ($option != 'check' && $option != 'whois') {
		$cont .= $domainopt._WHOIS_TEXT;
	} else {
		if (!namecheck($domainwhois)) {
			if (isset($serverdefs[$ext])) {
				$server = $serverdefs[$ext][0];
				$nomatch = $serverdefs[$ext][1];
				if ($option=='check') {
					$layout = checkdomain($domainwhois,$ext);
					$cont .= printresults($layout, 0);
				}
				if ($option=='whois') $cont .= whois($domainwhois,$ext);
			}
		} else {
			$cont .= printresults(namecheck($domainwhois), 0);
		}
	}
	echo $cont;
	setFoot();
}

function add(): void {
	global $db, $user, $conf, $stop, $tpl;
	if ((is_user() && $conf['whois']['add'] == 1) || (!is_user() && $conf['whois']['addquest'] == 1)) {
		setHead(['title' => _WHOIS_LICENS_SEND]);
		$cont = getModuleNavi(['title' => _WHOIS_LICENS_SEND] + WHOIS_NAVI);
        if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ABMIT]);
		$hometext = getVar('post', 'hometext', 'raw');
		$postname = getVar('post', 'postname', 'name');
		$postname = ($postname) ? $postname : _ANONYM;
		if (is_user()) {
			$userNameValue = filterText(substr($user[1], 0, 25));
		} else {
			$userNameValue = $postname;
		}
		$domain = getVar('post', 'domain', 'url', 'http://');
		$host = getVar('post', 'host', 'url', 'http://');
		$dc = getVar('post', 'dc', 'url', 'http://');

		$fields = $tpl->getHtmlFrag('form-field-row', ['label' => _SITE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'domain', 'value_attr' => $domain, 'maxlength_num' => '255', 'placeholder_text' => _SITE, 'is_required' => true])]);
		$fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _HOST, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'host', 'value_attr' => $host, 'maxlength_num' => '255', 'placeholder_text' => _HOST])]);
		$fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _DC, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'dc', 'value_attr' => $dc, 'maxlength_num' => '255', 'placeholder_text' => _DC])]);
		$cont .= $tpl->getHtmlPart('form-add', [
			'has_name'           => true,
			'is_user'            => is_user(),
			'name'               => $conf['name'],
			'token'              => htmlspecialchars(getSiteToken('whois'), ENT_QUOTES, 'UTF-8'),
			'lbl_name'           => _YOURNAME,
			'username'           => is_user() ? $userNameValue : '',
			'postname'           => $userNameValue,
			'fields_before_text' => $fields,
			'lbl_text'           => _COMMENT,
			'hometext'           => $tpl->getHtmlFrag('textarea', ['name_attr' => 'hometext', 'rows_num' => 5, 'value_text' => $hometext, 'placeholder_text' => _COMMENT]),
			'captcha'            => getCaptcha(1),
			'submit'             => $tpl->getHtmlFrag('form-submit', ['op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _SEND]),
		]);
		echo $cont;
		setFoot();
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function send(): void {
	global $db, $user, $conf, $stop, $tpl;
	if ((is_user() && $conf['whois']['add'] == 1) || (!is_user() && $conf['whois']['addquest'] == 1)) {
		$postname = getVar('post', 'postname', 'name');
		$domain = getVar('post', 'domain', 'url');
		$host = getVar('post', 'host', 'url');
		$dc = getVar('post', 'dc', 'url');
		$hometext = getVar('post', 'hometext', 'text');
		$stop = [];
		if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'whois')) $stop[] = _ERROR;
		if (!$postname && !is_user()) $stop[] = _CERROR3;
		if (!$domain) $stop[] = _CERROR4;
		if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
		if ($db->getSqlRowCount($db->getSqlQuery('SELECT domain FROM '.PREFIX_DB.'_whois WHERE domain = :domain', ['domain' => $domain])) > 0) $stop[] = _LINKEXIST;
		if (!$stop) {
            $postid = (is_user()) ? (int)$user[0] : '';
            $uname  = (!is_user()) ? $postname : '';
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_whois (id, uid, name, ip, time, domain, host, dc, body, sdomain, shost, sdc, status)'
                ." VALUES (NULL, :uid, :name, :ip, NOW(), :domain, :host, :dc, :body, '0', '0', '0', '0')",
                ['uid' => $postid, 'name' => $uname, 'ip' => getIp(),
                    'domain' => $domain, 'host' => $host, 'dc' => $dc, 'body' => $hometext]
            );
			$puname = (is_user()) ? $user[1] : $postname;
			addAdminMail($conf['whois']['addmail'], $conf['name'], $puname, _WHOIS);
			setHead(['title' => _WHOIS_LICENS_SEND]);
			$meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 10]);
            echo getModuleNavi(['title' => _WHOIS_LICENS_SEND] + WHOIS_NAVI).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _ABTEXT, 'meta' => $meta]);
			setFoot();
		} else {
			add();
		}
	} else {
		setRedirect('index.php?name='.$conf['name']);
	}
}

function checkdomain(string $domainwhois, string $ext): int {
	global $nomatch, $server;
	$output = '';
	set_error_handler(function() { return true; }, E_WARNING);
	$sc = fsockopen($server, 43, $errno, $errstr, 10);
	restore_error_handler();
	if ($sc === false) {
		return 2;
	}
	fputs($sc, $domainwhois.'.'.$ext."\n");
	while(!feof($sc)) {
		$output .= fgets($sc, 128);
	}
	fclose($sc);
	if (stripos($output, $nomatch) !== false) {
		return 0;
	} else {
		return 1;
	}
}

function whois(string $domainwhois, string $ext): string {
	global $server;
	$cont = '';
	set_error_handler(function() { return true; }, E_WARNING);
	$sc = fsockopen($server, 43, $errno, $errstr, 10);
	restore_error_handler();
	if ($sc === false) {
		$cont .= 'There is a temporary service disruption Please again try later';
		$layout = 2;
		$cont .= printresults($layout, 0);
		exit;
	}
	if ($ext=='com' || $ext=='net') {
		fputs($sc, $domainwhois.'.'.$ext."\n");
		while(!feof($sc)) {
			$temp = fgets($sc, 128);
			if (stripos($temp, 'Whois Server:') !== false) {
				$server = str_replace('Whois Server: ', '', $temp);
				$server = trim($server);
			}
		}
		fclose($sc);
		set_error_handler(function() { return true; }, E_WARNING);
		$sc = fsockopen($server, 43, $errno, $errstr, 10);
		restore_error_handler();
		if ($sc === false) {
			$layout = 2;
			$cont .= printresults($layout, 0);
			exit;
		}
	}
	$output = '';
	fputs($sc, $domainwhois.'.'.$ext."\n");
	while(!feof($sc)) {
		$output .= fgets($sc, 128);
	}
	fclose($sc);
	$cont .= printwhois($output);
	return $cont;
}

function printresults(int|string|null $layout, int $id): string {
	global $domainwhois, $ext, $server, $domainopt, $conf, $tpl;
	$cont = '';
	if (!$id) $cont .= $domainopt;
	$result = '';
	if ($layout=='0') {
		$result .= $tpl->getHtmlFrag('span', ['text' => _DOMAIN.' «'.$domainwhois.'.'.$ext.'» '._WHOIS_FREI.'!', 'is_success' => true]);
	} elseif($layout=='1') {
		$result .= $tpl->getHtmlFrag('span', ['text' => _DOMAIN.' «'.$domainwhois.'.'.$ext.'» '._WHOIS_B.'!', 'is_danger' => true])
		.$tpl->getHtmlPart('mini-post-form', [
			'action'        => 'index.php?name='.$conf['name'],
			'hidden_fields' => [
				['name_attr' => 'option', 'value_attr' => 'whois', 'input_attr' => ''],
				['name_attr' => 'domain_whois', 'value_attr' => $domainwhois, 'input_attr' => ''],
				['name_attr' => 'ext', 'value_attr' => $ext, 'input_attr' => ''],
			],
			'submit_label'  => _WHOIS_INF_US,
		]);
	} elseif($layout=='2') {
		$result .= $tpl->getHtmlFrag('span', ['text' => 'Could not contact the whois server '.$server, 'is_danger' => true]);
	} else {
		$result .= $tpl->getHtmlFrag('span', ['text' => $layout, 'is_danger' => true]);
	}
	$cont .= $tpl->getHtmlPart('fieldset-block', ['legend' => _WHOIS_SUCH, 'content' => $result, 'is_center' => true, 'is_info' => true]);
	return $cont;
}

function printwhois(string|array $output): string {
	global $domainwhois, $ext, $domainopt, $tpl;
	$lines = explode("\n", $output);
	return $tpl->getHtmlPart('whois-output', [
		'domain_form' => $domainopt,
		'legend'      => _WHOIS_INF_US,
		'lines'       => $lines,
		'is_info'     => true,
	]);
}

function namecheck(string $domainwhois): string|null {
	if ($domainwhois == '') return _WHOIS_FEL.'!';
	if (strlen($domainwhois) < 3) return _WHOIS_FEL1.'!';
	if (strlen($domainwhois) > 57) return _WHOIS_FEL2.'!';
	if (preg_match('#^-|-$#', $domainwhois)) return _WHOIS_FEL3.'!';
	if (preg_match('#[^a-zA-Z0-9._-]#', $domainwhois)) return _WHOIS_FEL4.'!';
	return null;
}

switch ($op) {
    default: mwhois(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
