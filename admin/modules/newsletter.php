<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function newsletter(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, title, mails, send, time, endtime FROM '.PREFIX_DB.'_newsletter ORDER BY id');
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        while ([$id, $title, $mails, $sended, $time, $endtime] = $db->getSqlRow($result)) {
            $sendtime = ($endtime > $time) ? strtotime($endtime) - strtotime($time) : 0;
            $active = ($mails && $sended && $conf['newsletter']['active']) ? 1 : 0;
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => [
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING), 'is_last' => false],
                            ['label' => _TIMENL, 'value' => getDuration($sendtime), 'is_last' => true],
                        ],
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['content_html' => $sended.' '._NLUSER],
                    ['content_html' => ad_status('', $active)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', [
                        'trigger_label' => _FUNCTIONS,
                        'items' => [[
                            'href' => $afile.'.php?name=newsletter&amp;op=add&amp;id='.$id,
                            'label' => _FULLEDIT,
                            'title' => _FULLEDIT,
                        ], [
                            'href' => $afile.'.php?name=newsletter&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                            'label' => _ONDELETE,
                            'title' => _ONDELETE,
                            'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                        ]],
                    ])],
                ],
            ])]);
        }
        $cont .= $tpl->getHtmlFrag('table', [
            'is_fixed' => true,
            'head' => [
                ['content' => _ID],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _NLEND],
                ['content' => _STATUS, 'nosort' => true],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT title, body, mails FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]);
        [$nid, $title, $body, $mails] = [$id, ...$db->getSqlRow($result)];
    } else {
        $nid = getVar('post', 'nid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', $conf['mtemp']);
        $mails = getVar('post', 'mails', '', '');
    }
    $stoptext = is_array($stop) ? implode(PHP_EOL, $stop) : (string)$stop;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stoptext !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stoptext]);
    if ($body) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $body, 'mod' => 'all']);
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users'));
    $option = $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MASSMAIL.' - '.$num, 'is_selected' => (string)$mails === '1']);
    [$num2] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_users WHERE newslet = 1'));
    $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _ANEWSLETTER.' - '.$num2, 'is_selected' => (string)$mails === '2']);
    $result3 = $db->getSqlQuery('SELECT id, name, points FROM '.PREFIX_DB.'_groups WHERE extra = 1 ORDER BY id');
    if ($db->getSqlRowCount($result3) > 0) {
        while ([$grid, $grname, $points] = $db->getSqlRow($result3)) {
            $result4 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE grp = :grid', ['grid' => $grid]);
            $email3 = '';
            $num3 = 0;
            while ([$user_email] = $db->getSqlRow($result4)) {
                $email3 .= $user_email.',';
                $num3++;
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email3, 'label_text' => _SPEC_GROUP.' "'.$grname.'" - '.$num3, 'is_selected' => $email3 === $mails]);
        }
    }
    $result5 = $db->getSqlQuery('SELECT id, name, points FROM '.PREFIX_DB.'_groups WHERE extra != 1 ORDER BY id');
    if ($db->getSqlRowCount($result5) > 0) {
        while ([$grid, $grname, $points] = $db->getSqlRow($result5)) {
            $result6 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE points >= :points', ['points' => $points]);
            $email4 = '';
            $num4 = 0;
            while ([$user_email] = $db->getSqlRow($result6)) {
                $email4 .= $user_email.',';
                $num4++;
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email4, 'label_text' => _GROUP.' "'.$grname.'" - '.$num4, 'is_selected' => $email4 === $mails]);
        }
    }
    if (is_active('money')) {
        $result7 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_money WHERE status = 1');
        if ($db->getSqlRowCount($result7) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result7)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email5 = '';
            $num5 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email5 .= $val.',';
                    $num5++;
                }
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email5, 'label_text' => _CLIENTSM.' "'._MONEY.'" - '.$num5, 'is_selected' => $email5 === $mails]);
        }
    }
    if (is_active('order')) {
        $result8 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_order WHERE status = 1');
        if ($db->getSqlRowCount($result8) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result8)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email6 = '';
            $num6 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email6 .= $val.',';
                    $num6++;
                }
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email6, 'label_text' => _CLIENTSM.' "'._ORDER.'" - '.$num6, 'is_selected' => $email6 === $mails]);
        }
    }
    if (is_active('shop')) {
        $result9 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients');
        if ($db->getSqlRowCount($result9) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result9)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email7 = '';
            $num7 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email7 .= $val.',';
                    $num7++;
                }
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email7, 'label_text' => _CLIENTSM.' "'._SHOP.'" ('._ALL.') - '.$num7, 'is_selected' => $email7 === $mails]);
        }
        $result10 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients WHERE status = 1');
        if ($db->getSqlRowCount($result10) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result10)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email8 = '';
            $num8 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email8 .= $val.',';
                    $num8++;
                }
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email8, 'label_text' => _CLIENTSM.' "'._SHOP.'" ('._AKTIVE.') - '.$num8, 'is_selected' => $email8 === $mails]);
        }
        $result11 = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_clients WHERE status = 0');
        if ($db->getSqlRowCount($result11) > 0) {
            $aemail = [];
            while ([$user_email] = $db->getSqlRow($result11)) $aemail[] = $user_email;
            $aemail = array_unique($aemail);
            $email9 = '';
            $num9 = 0;
            foreach ($aemail as $val) {
                if ($val != '') {
                    $email9 .= $val.',';
                    $num9++;
                }
            }
            $option .= $tpl->getHtmlFrag('select-option', ['value_attr' => $email9, 'label_text' => _CLIENTSM.' "'._SHOP.'" ('._DEAKTIVE.') - '.$num9, 'is_selected' => $email9 === $mails]);
        }
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'nid', 'valueattr' => (string)$nid],
            ['nameattr' => 'name', 'valueattr' => 'newsletter'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'posttype', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [
            ['label_html' => _TITLE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 50, 'placeholder_text' => _TITLE, 'is_required' => true])],
            ['label_html' => _NLWHERE.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'mails', 'options_html' => $option])],
            ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'body', 'value' => $body, 'mod' => 'all', 'rows' => '10', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ],
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $stop = [];
    $nid = getVar('post', 'nid', 'num', 0);
    $title = getVar('post', 'title', 'title');
    $body = getVar('post', 'body', 'text');
    $mails = getVar('post', 'mails', '');
    $warn = !checkSiteToken();
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if (!$warn && !$stop && getVar('post', 'posttype') == 'save') {
        if ($mails == 1) {
            $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users');
            $emails = [];
            while ([$user_email] = $db->getSqlRow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } elseif ($mails == 2) {
            $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE newslet = 1');
            $emails = [];
            while ([$user_email] = $db->getSqlRow($result)) $emails[] = $user_email;
            $emails = implode(',', array_unique($emails));
        } else {
            $emails = $mails;
        }
        if ($nid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_newsletter SET title = :title, body = :body, mails = :mails, send = 0, time = now(), endtime = 0 WHERE id = :id', [
                'title' => $title, 'body' => $body, 'mails' => $emails, 'id' => $nid
            ]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_newsletter (title, body, mails, send, time, endtime) VALUES (:title, :body, :mails, 0, now(), 0)', [
                'title' => $title, 'body' => $body, 'mails' => $emails
            ]);
        }
        setRedirect($afile.'.php?name=newsletter', false, 302, _SUCCSAVE);
    } elseif ($warn) {
        setRedirect($afile.'.php?name=newsletter&amp;op=add'.($nid ? '&amp;id='.$nid : ''), false, 302, _TOKENMISS, true);
    } else {
        add();
    }
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $warn = !checkSiteToken();
    if (!$warn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=newsletter', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/newsletter.php');
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'newsletter'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [
            ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _NLSEND, 'hint' => _NLSENDI]), 'field_html' => getTplRadioGroup(['name' => 'active', 'value' => (string)(int)$conf['newsletter']['active'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
            ['label_html' => _NLCOUNT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'count', 'value_attr' => (string)$conf['newsletter']['count'], 'is_config' => true])],
        ],
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function configsave(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $content = [
            'active' => getVar('post', 'active', 'num', 0),
            'count'  => getVar('post', 'count', 'num', 4),
        ];
        setConfigFile('newsletter.php', $content);
    }
    setRedirect($afile.'.php?name=newsletter&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=newsletter', 'name=newsletter&amp;op=add', 'name=newsletter&amp;op=config', 'name=newsletter&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: newsletter(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
