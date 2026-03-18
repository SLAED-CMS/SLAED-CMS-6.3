<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('voting')) die('Illegal file access');

function voting(): void {
    global $db, $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=conf', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['voting']['anum'];
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT id, modul, time, enddate, title, lang, typ FROM '.PREFIX_DB.'_voting ORDER BY id DESC LIMIT '.$offset.', '.$conf['voting']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th>';
        if ($conf['multilingual'] == 1) $cont .= '<th>'._LANGUAGE.'</th>';
        $cont .= '<th>'._MODUL.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $modul, $date, $enddate, $title, $lang, $typ] = $db->getSqlRow($result)) {
            if (time() >= strtotime($date) && time() <= strtotime($enddate)) {
                $view = (!$modul) ? '<a href="index.php?name=voting&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||' : '';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $type = ($typ == '1') ? _VOPEN : _VCLOSE;
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CHNGSTORY.': '.format_time($date, _TIMESTRING).'<br>'._ENDDATE.': '.format_time($enddate, _TIMESTRING).'<br>'._TYPE.': '.$type).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>';
            if ($conf['multilingual'] == 1) {
                $lang = (!$lang) ? _ALL : $lang;
                $cont .= '<td>'.getLangName($lang).'</td>';
            }
            $mod = ($modul) ? getModuleName($modul) : _NONE;
            $cont .= '<td>'.$mod.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=voting&amp;op=delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $conf['voting']['anum'], 'name=voting&amp;', 'id', '_voting', '', '', $conf['voting']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop;
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
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=conf', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($id) $cont .= setTemplateBasic('open').'<div id="repvoting">'.getVoting($id, 'voting').'</div>'.setTemplateBasic('close');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">';
    $mname = ['news', 'shop'];
    $content = '';
    foreach ($mname as $val) {
        if ($val != '') {
            $sel = ($modul == $val) ? ' selected' : '';
            $content .= '<option value="'.$val.'"'.$sel.'>'.getModuleName($val).'</option>';
        }
    }
    $cont .= '<tr><td>'._MODUL.':</td><td><select name="modul" class="sl_form"><option value="">'._NO.'</option>'.$content.'</select></td></tr>'
    .'<tr><td>'._TITLE.' / '._POLLTITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.' / '._POLLTITLE.'" required></td></tr>'
    .'<tr><td colspan="2">';
    $i = 0;
    while ($i < $conf['voting']['answ']) {
        $a = $i + 1;
        $question = $body[$i] ?? '';
        $ansval = $answer[$i] ?? '';
        $class = ($i != 0 && $question == '') ? ' class="sl_none"' : '';
        $cont .= '<table id="vot'.$i.'"'.$class.'><tr><td><a OnClick="HideShow(\'vot'.$a.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._POLLEACH.' - '.$a.':</a></td><td class="sl_form"><input type="text" name="body[]" value="'.filterText($question).'" style="width: 375px;" class="sl_field" placeholder="'._POLLEACH.' - '.$a.'"> '._VOTES.': <input type="text" name="answer[]" value="'.filterText($ansval).'" style="width: 40px;" class="sl_field" placeholder="'._VOTES.'"></td></tr></table>';
        $i++;
    }
    $cont .= '</td></tr>'
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'date', $date, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._ENDDATE.':</td><td>'.datetime(1, 'enddate', $enddate, 16, 'sl_form').'</td></tr>'
    .'<tr><td>'._AFTEREXPIRATION.':</td><td><select name="status" class="sl_form">'
    .'<option value="1"';
    if ($status == '1') $cont .= ' selected';
    $cont .= '>'._VCLOSED.'</option>'
    .'<option value="0"';
    if ($status == '0') $cont .= ' selected';
    $cont .= '>'._VDEACT.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._TYPE.':</td><td><select name="typ" class="sl_form">'
    .'<option value="1"';
    if ($typ == '1') $cont .= ' selected';
    $cont .= '>'._VOPEN.'</option>'
    .'<option value="0"';
    if ($typ == '0') $cont .= ' selected';
    $cont .= '>'._VCLOSE.'</option>'
    .'</select></td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language($lang).'</select></td></tr>';
    $cont .= '<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._MULTI.'</td><td>'.radio_form($multi, 'multi').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="voting">'.ad_save('id', $id, 'save', 1).'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=conf', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/voting.php');
    $bval = (string)($conf['voting']['block'] ?? '0');
    $block_sel = '<select name="block" class="sl_conf">'
        .'<option value="0"'.($bval === '0' ? ' selected' : '').'>'._VLASTACT.'</option>'
        .'<option value="1"'.($bval === '1' ? ' selected' : '').'>'._VLASTCLO.'</option>'
        .'<option value="2"'.($bval === '2' ? ' selected' : '').'>'._VRANACT.'</option>'
        .'<option value="3"'.($bval === '3' ? ' selected' : '').'>'._VRANCLO.'</option>'
        .'</select>';
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'         => $afile,
        '{%module%}'        => 'voting',
        '{%op%}'            => 'saveconf',
        '{%save%}'          => _SAVECHANGES,
        '{%fields%}'        => '',
        '{%_voting_time%}'  => _VOTING_TIME,
        '{%time%}'          => intval($conf['voting']['voting_t'] / 86400),
        '{%_c33%}'          => _C_33,
        '{%num%}'           => $conf['voting']['num'],
        '{%_c34%}'          => _C_34,
        '{%anum%}'          => $conf['voting']['anum'],
        '{%_c35%}'          => _C_35,
        '{%nump%}'          => $conf['voting']['nump'],
        '{%_c36%}'          => _C_36,
        '{%anump%}'         => $conf['voting']['anump'],
        '{%_vansw%}'        => _VANSW,
        '{%answ%}'          => $conf['voting']['answ'],
        '{%_vblock%}'       => _VBLOCK,
        '{%s_block%}'       => $block_sel,
        'if_flag'           => ['voting' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
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
    setRedirect($afile.'.php?name=voting&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=conf', 'name=voting&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: voting(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}





