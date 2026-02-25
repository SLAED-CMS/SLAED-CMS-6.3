<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('voting')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=voting', 'name=voting&amp;op=add', 'name=voting&amp;op=conf', 'name=voting&amp;op=info'];
    $lang = [_HOME, _ADD, _PREFERENCES, _INFO];
    return getAdminTabs(_VOTING, 'voting.png', '', $ops, $lang, [], [], $tab, (bool)$subtab);
}

function voting(): void {
    global $db, $afile, $conf, $confv;
    head();
    $cont = navi(0, 0, 0, 0);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $confv['anum'];
    $offset = intval($offset);
    $result = $db->sql_query('SELECT id, modul, date, enddate, title, language, typ FROM '.PREFIX_DB.'_voting ORDER BY id DESC LIMIT '.$offset.', '.$confv['anum']);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th>';
        if ($conf['multilingual'] == 1) $cont .= '<th>'._LANGUAGE.'</th>';
        $cont .= '<th>'._MODUL.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $modul, $date, $enddate, $title, $language, $typ] = $db->sql_fetchrow($result)) {
            if (time() >= strtotime($date) && time() <= strtotime($enddate)) {
                $ad_view = (!$modul) ? '<a href="index.php?name=voting&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||' : '';
                $active = '1';
            } else {
                $ad_view = '';
                $active = '0';
            }
            $type = ($typ == '1') ? _VOPEN : _VCLOSE;
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_CHNGSTORY.': '.format_time($date, _TIMESTRING).'<br>'._ENDDATE.': '.format_time($enddate, _TIMESTRING).'<br>'._TYPE.': '.$type).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>';
            if ($conf['multilingual'] == 1) {
                $language = (!$language) ? _ALL : $language;
                $cont .= '<td>'.deflang($language).'</td>';
            }
            $mod = ($modul) ? deflmconst($modul) : _NONE;
            $cont .= '<td>'.$mod.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($ad_view.'<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=voting&amp;op=delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $confv['anum'], 'name=voting&amp;', 'id', '_voting', '', '', $confv['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $conf, $confv, $stop;
    $stop = $stop ?? '';
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->sql_query('SELECT id, modul, title, questions, answer, date, enddate, multi, language, acomm, typ, status FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
        [$id, $modul, $title, $questions, $answer, $date, $enddate, $multi, $language, $acomm, $typ, $status] = $db->sql_fetchrow($result);
        $questions = explode('|', $questions);
        $answer = explode('|', $answer);
    } else {
        $modul = getVar('post', 'modul', 'text', '');
        $title = getVar('post', 'title', 'text', '');
        $questions = getVar('post', 'questions', 'array', []);
        $answer = getVar('post', 'answer', 'array', []);
        $date = save_datetime(1, 'date');
        $enddate = save_datetime(1, 'enddate');
        $multi = getVar('post', 'multi', 'num', 0);
        $language = getVar('post', 'language', 'text', '');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $typ = getVar('post', 'typ', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($id) $cont .= setTemplateBasic('open').'<div id="repvoting">'.getVoting($id, 'voting').'</div>'.setTemplateBasic('close');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">';
    $mname = ['news', 'shop'];
    $content = '';
    foreach ($mname as $val) {
        if ($val != '') {
            $sel = ($modul == $val) ? ' selected' : '';
            $content .= '<option value="'.$val.'"'.$sel.'>'.deflmconst($val).'</option>';
        }
    }
    $cont .= '<tr><td>'._MODUL.':</td><td><select name="modul" class="sl_form"><option value="">'._NO.'</option>'.$content.'</select></td></tr>'
    .'<tr><td>'._TITLE.' / '._POLLTITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.' / '._POLLTITLE.'" required></td></tr>'
    .'<tr><td colspan="2">';
    $i = 0;
    while ($i < $confv['answ']) {
        $a = $i + 1;
        $question = $questions[$i] ?? '';
        $ansval = $answer[$i] ?? '';
        $class = ($i != 0 && $question == '') ? ' class="sl_none"' : '';
        $cont .= '<table id="vot'.$i.'"'.$class.'><tr><td><a OnClick="HideShow(\'vot'.$a.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._POLLEACH.' - '.$a.':</a></td><td class="sl_form"><input type="text" name="questions[]" value="'.text_filter($question).'" style="width: 375px;" class="sl_field" placeholder="'._POLLEACH.' - '.$a.'"> '._VOTES.': <input type="text" name="answer[]" value="'.text_filter($ansval).'" style="width: 40px;" class="sl_field" placeholder="'._VOTES.'"></td></tr></table>';
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
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="language" class="sl_form">'.language($language).'</select></td></tr>';
    $cont .= '<tr><td>'._COMMENTS.':</td><td>'.com_access('acomm', $acomm, 'sl_form').'</td></tr>'
    .'<tr><td>'._MULTI.'</td><td>'.radio_form($multi, 'multi').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="voting">'.ad_save('id', $id, 'save', 1).'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num', 0);
    $modul = analyze(getVar('post', 'modul', 'text', ''));
    $title = getVar('post', 'title', 'text', '');
    $questions = getVar('post', 'questions', 'array', []);
    $answer = getVar('post', 'answer', 'array', []);
    $quest = [];
    $answ = [];
    for ($q = 0; $q < count($questions); $q++) {
        if ($questions[$q] != '') {
            $quest[] = $questions[$q];
            $answ[] = (is_numeric($answer[$q] ?? '')) ? (string)$answer[$q] : '0';
        }
    }
    $quest = is_array($quest) ? implode('|', $quest) : '';
    $answ = is_array($answ) ? implode('|', $answ) : '';
    $date = save_datetime(1, 'date');
    $enddate = save_datetime(1, 'enddate');
    $multi = getVar('post', 'multi', 'num', 0);
    $language = getVar('post', 'language', 'text', '');
    $acomm = ($modul) ? '0' : getVar('post', 'acomm', 'num', 0);
    $typ = getVar('post', 'typ', 'num', 0);
    $status = (!$typ) ? '0' : getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype == 'save') {
        if ($id) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_voting SET modul = :modul, title = :title, questions = :quest, answer = :answ, date = :date, enddate = :enddate, multi = :multi, language = :language, acomm = :acomm, typ = :typ, status = :status WHERE id = :id', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'date' => $date, 'enddate' => $enddate, 'multi' => $multi, 'language' => $language, 'acomm' => $acomm, 'typ' => $typ, 'status' => $status, 'id' => $id]);
        } else {
            $ip = getIp();
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_voting (id, modul, title, questions, answer, date, enddate, multi, language, acomm, ip, typ, status) VALUES (NULL, :modul, :title, :quest, :answ, :date, :enddate, :multi, :language, :acomm, :ip, :typ, :status)', ['modul' => $modul, 'title' => $title, 'quest' => $quest, 'answ' => $answ, 'date' => $date, 'enddate' => $enddate, 'multi' => $multi, 'language' => $language, 'acomm' => $acomm, 'ip' => $ip, 'typ' => $typ, 'status' => $status]);
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
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'voting\'', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=voting', true);
}

function conf(): void {
    global $afile, $confv;
    head();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms('voting.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._VOTING_TIME.':</td><td><input type="number" name="voting_t" value="'.intval($confv['voting_t'] / 86400).'" class="sl_conf" placeholder="'._VOTING_TIME.'" required></td></tr>'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$confv['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$confv['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$confv['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$confv['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._VANSW.':</td><td><input type="number" name="answ" value="'.$confv['answ'].'" class="sl_conf" placeholder="'._VANSW.'" required></td></tr>'
    .'<tr><td>'._VBLOCK.':</td><td><select name="block" class="sl_conf">'
    .'<option value="0"';
    if ($confv['block'] == '0') $cont .= ' selected';
    $cont .= '>'._VLASTACT.'</option>'
    .'<option value="1"';
    if ($confv['block'] == '1') $cont .= ' selected';
    $cont .= '>'._VLASTCLO.'</option>'
    .'<option value="2"';
    if ($confv['block'] == '2') $cont .= ' selected';
    $cont .= '>'._VRANACT.'</option>'
    .'<option value="3"';
    if ($confv['block'] == '3') $cont .= ' selected';
    $cont .= '>'._VRANCLO.'</option>'
    .'</select></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="voting"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
    global $afile;
    $cont = [
        'voting_t' => getVar('post', 'voting_t', 'num', 1) * 86400,
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
    head();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.adm_info(1, 'voting', 0).'</div>';
    foot();
}

switch ($op) {
    default: voting(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
