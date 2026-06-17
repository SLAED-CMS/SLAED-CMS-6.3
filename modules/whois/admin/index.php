<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('whois')) die('Illegal file access');

function whois(): void {
    global $db, $afile, $conf, $tpl;
    $anum = $conf['whois']['anum'] ?? 10;
    $anump = $conf['whois']['anump'] ?? 10;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $offset = (int)(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num');
    if ($status == 1) {
        $status = 0;
        $field = 'name=whois&amp;status=1&amp;';
        $cont = getTplAdminTabs([
            'ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'],
            'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
            'tab' => 2,
        ]);
    } else {
        $status = 1;
        $field = 'name=whois&amp;';
        $cont = getTplAdminTabs([
            'ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'],
            'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
        ]);
    }
    $result = $db->getSqlQuery('SELECT w.id, w.name, w.ip, w.time, w.domain, w.host, w.dc, w.body, w.sdomain, w.shost, w.sdc, u.name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.id) WHERE status = :status ORDER BY w.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _SITE, 'is_truncate' => true],
            ['content' => _HOST, 'is_truncate' => true],
            ['content' => _DC, 'is_truncate' => true],
            ['content' => _POSTEDBY, 'is_truncate' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ];
        $rows = '';
        while ([$id, $uname, $ipSender, $time, $domain, $host, $dc, $hometext, $statusDomain, $statusHost, $statusDc, $userName] = $db->getSqlRow($result)) {
            $post = $userName ? user_info($userName) : ($uname ?: _ANONYM);
            $postname = $userName ?: ($uname ?: _ANONYM);
            $ipSender = $ipSender ? Geoip::getIpHtml($ipSender) : _NO;
            $hometext = $hometext ?: _NO;
            $host = $host ? domain($host) : _NO_INFO;
            $dc = $dc ? domain($dc) : _NO_INFO;
            $domain = domain($domain);
            $items = [
                ['href' => $afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=1&amp;refer=1&amp;token='.getSiteToken(), 'label' => _SITE, 'title' => _SITE],
                ['href' => $afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=2&amp;refer=1&amp;token='.getSiteToken(), 'label' => _HOST, 'title' => _HOST],
                ['href' => $afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=3&amp;refer=1&amp;token='.getSiteToken(), 'label' => _DC, 'title' => _DC],
                ['href' => $afile.'.php?name=whois&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
                ['href' => $afile.'.php?name=whois&amp;op=delete&amp;id='.$id.'&amp;refer=1&amp;token='.getSiteToken(), 'label' => _ONDELETE, 'title' => _ONDELETE, 'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($domain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"'],
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['is_col_id' => true, 'content_html' => (string)$id],
                ['is_truncate' => true, 'title_text' => $domain, 'content_html' => $domain.' '.ad_status('', $statusDomain)],
                ['is_truncate' => true, 'title_text' => $host, 'content_html' => $host.' '.ad_status('', $statusHost)],
                ['is_truncate' => true, 'title_text' => $dc, 'content_html' => $dc.' '.ad_status('', $statusDc)],
                ['is_truncate' => true, 'title_text' => $postname, 'content_html' => $tpl->getHtmlFrag('popover', ['items' => [
                    ['label' => _DATE, 'value' => format_time($time, _TIMESTRING), 'is_last' => false],
                    ['label' => _IP, 'value' => $ipSender, 'is_last' => false],
                    ['label' => _COMMENT, 'has_value_text' => true, 'value_text' => (string)$hometext, 'is_last' => true],
                ]]).$post],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
            ]])]);
        }
        $body = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_whois', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function toggle(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    $id = getVar('get', 'id', 'num');
    $fid = getVar('get', 'fid', 'num');
    $field = match ($fid) {
        1 => 'sdomain',
        2 => 'shost',
        3 => 'sdc',
        default => '',
    };
    if (!$iswarn && $id && $field) {
        [$active] = $db->getSqlRow($db->getSqlQuery('SELECT '.$field.' FROM '.PREFIX_DB.'_whois WHERE id = :id', ['id' => $id]));
        $active = $active ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_whois SET '.$field.' = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=whois', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $stop = $stop ?? [];
    $wid = getVar('post', 'wid', 'num', 0);
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $wid = $id;
        $result = $db->getSqlQuery('SELECT w.id, w.name, w.domain, w.host, w.dc, w.body, u.name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.id) WHERE w.id = :id', ['id' => $id]);
        [$id, $uname, $domain, $host, $dc, $hometext, $userName] = $db->getSqlRow($result);
        $postname = $userName ?: ($uname ?: _ANONYM);
    } else {
        $postname = getVar('post', 'postname', 'name', '');
        $domain = getVar('post', 'domain', 'url', 'http://');
        $host = getVar('post', 'host', 'url', 'http://');
        $dc = getVar('post', 'dc', 'url', 'http://');
        $hometext = getVar('post', 'hometext', 'text', '');
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
        'tab' => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    $rows = [
        ['label_html' => _POSTEDBY, 'field_html' => getTplUserSearchInput(['name' => 'postname', 'input_id' => 'postname', 'list_id' => 'postname_list', 'value' => $postname])],
        ['label_html' => _SITE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'domain', 'value_attr' => $domain, 'is_required' => true])],
        ['label_html' => _HOST, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'host', 'value_attr' => $host])],
        ['label_html' => _DC, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'dc', 'value_attr' => $dc])],
        ['label_html' => _COMMENT, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'hometext', 'value_text' => $hometext, 'rows_num' => 5]), 'is_full' => true],
    ];
    $actions = $tpl->getHtmlFrag('button', ['label' => _SAVECHANGES, 'button_attr' => ' onclick="this.form.elements[\'posttype\'].value=\'save\'; this.form.submit();"']);
    if ($wid) {
        $actions .= $tpl->getHtmlFrag('button', ['label' => _DELETE, 'button_attr' => ' onclick="this.form.elements[\'posttype\'].value=\'delete\'; this.form.submit();"']);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'whois'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'wid', 'valueattr' => (string)$wid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ['nameattr' => 'posttype', 'valueattr' => 'save'],
        ],
        'rows' => $rows,
        'actions_html' => $actions,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $iswarn = !checkSiteToken();
    $wid = getVar('post', 'wid', 'num');
    $postname = getVar('post', 'postname', 'name', '');
    $domain = getVar('post', 'domain', 'url', '');
    $host = getVar('post', 'host', 'url', '');
    $dc = getVar('post', 'dc', 'url', '');
    $hometext = getVar('post', 'hometext', 'text', '');
    $posttype = getVar('post', 'posttype', 'var', '');
    $stop = [];
    if (!$postname) $stop[] = _CERROR3;
    if (!$domain) $stop[] = _CERROR4;
    if ($iswarn) {
        setRedirect($afile.'.php?name=whois', false, 302, _TOKENMISS, true);
    } elseif (!$stop && $posttype == 'save') {
        $postid = is_user_id($postname);
        $uid = $postid ? $postid : '';
        $name = $postid ? '' : filterText(substr($postname, 0, 25));
        if ($wid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB."_whois SET uid = :uid, name = :name, domain = :domain, host = :host, dc = :dc, body = :body, status = '1' WHERE id = :id", ['uid' => $uid, 'name' => $name, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'body' => $hometext, 'id' => $wid]);
        } else {
            $ip = getIp();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_whois (id, uid, name, ip, time, domain, host, dc, body, sdomain, shost, sdc, status) VALUES (NULL, :uid, :name, :ip, now(), :domain, :host, :dc, :body, '0', '0', '0', '1')", ['uid' => $uid, 'name' => $name, 'ip' => $ip, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'body' => $hometext]);
        }
        setRedirect($afile.'.php?name=whois', false, 302, _SUCCSAVE, false);
    } elseif ($posttype == 'delete') {
        delete($wid);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$id) $id = getVar('req', 'id', 'num');
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_whois WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=whois', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
        'tab' => 3,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/whois.php');
    $rows = [
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['whois']['anum'] ?? 10), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['whois']['anump'] ?? 10), 'is_config' => true])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => $conf['whois']['addmail'] ?? 0, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _WHOISADD, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => $conf['whois']['add'] ?? 0, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _WHOISADDG, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => $conf['whois']['addquest'] ?? 0, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'whois'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $cont = [
            'anum' => getVar('post', 'anum', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'addmail' => getVar('post', 'addmail', 'num', 0),
            'add' => getVar('post', 'add', 'num', 0),
            'addquest' => getVar('post', 'addquest', 'num', 0),
        ];
        setConfigFile('whois.php', $cont);
    }
    setRedirect($afile.'.php?name=whois&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: whois(); break;
    case 'toggle': toggle(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
