<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('voting')) die('Illegal file access');

function voting(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=config', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['voting']['anum'];
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT id, modul, time, enddate, title, lang, typ FROM '.PREFIX_DB.'_voting ORDER BY id DESC LIMIT '.$offset.', '.$conf['voting']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-voting-list-head', [
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'lang_label' => _LANGUAGE,
            'modul_label' => _MODUL,
            'show_lang' => $conf['multilingual'] == 1,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $modul, $date, $enddate, $title, $lang, $typ] = $db->getSqlRow($result)) {
            if (time() >= strtotime($date) && time() <= strtotime($enddate)) {
                $view = (!$modul) ? adminLinkAction('index.php?name=voting&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW) : '';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $type = ($typ == '1') ? _VOPEN : _VCLOSE;
            $acts = adminMenuItems([
                $view,
                adminLinkAction($afile.'.php?name=voting&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=voting&amp;op=delete&amp;id='.$id.'&amp;refer=1', _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $langtext = '';
            if ($conf['multilingual'] == 1) $langtext = getLangName((!$lang) ? _ALL : $lang);
            $mod = ($modul) ? getModuleName($modul) : _NONE;
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-voting-list-row', [
                'actions_html' => $acts,
                'id_text' => (string)$id,
                'lang_text' => $langtext,
                'modul_text' => $mod,
                'show_lang' => $conf['multilingual'] == 1,
                'status_html' => ad_status('', $active),
                'title_html' => title_tip(_CHNGSTORY.': '.format_time($date, _TIMESTRING).'<br>'._ENDDATE.': '.format_time($enddate, _TIMESTRING).'<br>'._TYPE.': '.$type).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span>',
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $conf['voting']['anum'], 'name=voting&amp;', 'id', '_voting', '', '', $conf['voting']['anump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $stop = $stop ?? '';
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, modul, title, body, answer, time, enddate, multi, lang, acomm, typ, status FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
        [$id, $modul, $title, $body, $answer, $date, $enddate, $multi, $lang, $acomm, $typ, $status] = $db->getSqlRow($result);
        $body = explode('|', $body);
        $answer = explode('|', $answer);
    } else {
        $modul = getVar('post', 'modul', 'text', '');
        $title = getVar('post', 'title', 'text', '');
        $body = getVar('post', 'body', 'array', []);
        $answer = getVar('post', 'answer', 'array', []);
        $date = getVar('req', 'date', 'time');
        $enddate = getVar('req', 'enddate', 'time');
        $multi = getVar('post', 'multi', 'num', 0);
        $lang = getVar('post', 'lang', 'text', '');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $typ = getVar('post', 'typ', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=config', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    if ($id) $cont .= $tpl->getHtmlFrag('admin-voting-preview-box', ['voting_html' => getVoting($id, 'voting')]);
    $hide = getAdminHidden('name', 'voting');
    $mname = ['news', 'shop'];
    $content = getAdminOption('', _NO);
    foreach ($mname as $val) {
        if ($val != '') {
            $content .= getAdminOption($val, getModuleName($val), $modul == $val);
        }
    }
    $quest = '';
    $i = 0;
    while ($i < $conf['voting']['answ']) {
        $a = $i + 1;
        $question = $body[$i] ?? '';
        $ansval = $answer[$i] ?? '';
        $quest .= $tpl->getHtmlFrag('admin-voting-answer-row', [
            'add_label' => _ADD,
            'answer_value' => filterText($ansval),
            'block_id' => 'vot'.$i,
            'hidden' => $i != 0 && $question == '',
            'index_text' => (string)$a,
            'next_id' => 'vot'.$a,
            'poll_each_label' => _POLLEACH.' - '.$a.':',
            'question_placeholder' => _POLLEACH.' - '.$a,
            'question_value' => filterText($question),
            'votes_label' => _VOTES.':',
        ]);
        $i++;
    }
    $stat = getAdminSelect('status',
        getAdminOption('1', _VCLOSED, $status == '1') .
        getAdminOption('0', _VDEACT, $status == '0'));
    $type = getAdminSelect('typ',
        getAdminOption('1', _VOPEN, $typ == '1') .
        getAdminOption('0', _VCLOSE, $typ == '0'));
    $rows = $tpl->getHtmlFrag('admin-voting-add-rows', [
        'acomm_html' => com_access('acomm', $acomm, 'sl_form'),
        'acomm_label' => _COMMENTS.':',
        'answers_html' => $quest,
        'date_html' => datetime(1, 'date', $date, 16, 'sl_form'),
        'date_label' => _CHNGSTORY.':',
        'enddate_html' => datetime(1, 'enddate', $enddate, 16, 'sl_form'),
        'enddate_label' => _ENDDATE.':',
        'lang_html' => $conf['multilingual'] == 1 ? getAdminSelect('lang', language($lang), 'sl_form') : '',
        'lang_label' => _LANGUAGE.':',
        'modul_html' => getAdminSelect('modul', $content, 'sl_form'),
        'modul_label' => _MODUL.':',
        'multi_html' => radio_form($multi, 'multi'),
        'multi_label' => _MULTI,
        'save_html' => ad_save('id', $id, 'save', 1),
        'show_lang' => $conf['multilingual'] == 1,
        'status_html' => $stat,
        'status_label' => _AFTEREXPIRATION.':',
        'title_label' => _TITLE.' / '._POLLTITLE.':',
        'title_value' => $title,
        'type_html' => $type,
        'type_label' => _TYPE.':',
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num', 0);
    $modul = filterVar(getVar('post', 'modul', 'text', ''));
    $title = getVar('post', 'title', 'text', '');
    $body = getVar('post', 'body', 'array', []);
    $answer = getVar('post', 'answer', 'array', []);
    $quest = [];
    $answ = [];
    for ($q = 0; $q < count($body); $q++) {
        if ($body[$q] != '') {
            $quest[] = $body[$q];
            $answ[] = (is_numeric($answer[$q] ?? '')) ? (string)$answer[$q] : '0';
        }
    }
    $quest = is_array($quest) ? implode('|', $quest) : '';
    $answ = is_array($answ) ? implode('|', $answ) : '';
    $date = getVar('req', 'date', 'time');
    $enddate = getVar('req', 'enddate', 'time');
    $multi = getVar('post', 'multi', 'num', 0);
    $lang = getVar('post', 'lang', 'text', '');
    $acomm = ($modul) ? '0' : getVar('post', 'acomm', 'num', 0);
    $typ = getVar('post', 'typ', 'num', 0);
    $status = (!$typ) ? '0' : getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype == 'save') {
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_voting SET modul = :modul, title = :title, body = :quest, answer = :answ, time = :time, enddate = :enddate, multi = :multi, lang = :lang, acomm = :acomm, typ = :typ, status = :status WHERE id = :id', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate, 'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'typ' => $typ, 'status' => $status, 'id' => $id]);
        } else {
            $ip = getIp();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_voting (id, modul, title, body, answer, time, enddate, multi, lang, acomm, ip, typ, status) VALUES (NULL, :modul, :title, :quest, :answ, :time, :enddate, :multi, :lang, :acomm, :ip, :typ, :status)', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'time' => $date, 'enddate' => $enddate, 'multi' => $multi, 'lang' => $lang, 'acomm' => $acomm, 'ip' => $ip, 'typ' => $typ, 'status' => $status]);
        }
        setRedirect($afile.'.php?name=voting');
    } elseif ($posttype == 'delete') {
        delete($id);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'voting\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=voting', true);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=config', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/voting.php');
    $bval = (string)($conf['voting']['block'] ?? '0');
    $block_sel = getAdminSelect('block',
        getAdminOption('0', _VLASTACT, $bval === '0') .
        getAdminOption('1', _VLASTCLO, $bval === '1') .
        getAdminOption('2', _VRANACT, $bval === '2') .
        getAdminOption('3', _VRANCLO, $bval === '3'),
        'sl_conf');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'voting',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_voting_time' => _VOTING_TIME,
        'time' => intval($conf['voting']['voting_t'] / 86400),
        '_c33' => _C_33,
        'num' => $conf['voting']['num'],
        '_c34' => _C_34,
        'anum' => $conf['voting']['anum'],
        '_c35' => _C_35,
        'nump' => $conf['voting']['nump'],
        '_c36' => _C_36,
        'anump' => $conf['voting']['anump'],
        '_vansw' => _VANSW,
        'answ' => $conf['voting']['answ'],
        '_vblock' => _VBLOCK,
        's_block' => $block_sel,
        'voting' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'voting_t' => getVar('post', 'time', 'num', 1) * 86400,
        'num' => getVar('post', 'num', 'num', 10),
        'anum' => getVar('post', 'anum', 'num', 10),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'answ' => getVar('post', 'answ', 'num', 10),
        'block' => getVar('post', 'block', 'num', 0),
    ];
    setConfigFile('voting.php', $cont);
    setRedirect($afile.'.php?name=voting&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=config', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: voting(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}


