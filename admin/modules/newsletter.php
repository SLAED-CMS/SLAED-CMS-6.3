<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# Resolve one campaign state to the word an operator reads for it, in the vocabulary the queue uses everywhere else
function getCampState(int $stat): string {
    $map = [0 => _NLSTDRAFT, 1 => _NLSTPREP, 2 => _NLSTQUEUE, 3 => _NLSTCANAR, 4 => _NLSTHELD, 5 => _NLSTRUN, 6 => _NLSTDONE, 7 => _NLSTSTOP];
    return $map[$stat] ?? _NLSTDRAFT;
}

# Describe the stored audience criterion of one mailing, because the addresses it expanded to are no longer kept anywhere
function getCampAudit(string $audit, string $apar): string {
    global $db;
    if ($audit === 'group') {
        $row = $db->getSqlRow($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => intval($apar)]));
        return _GROUP.' "'.($row ? (string)$row['name'] : $apar).'"';
    }
    $map = ['all' => _MASSMAIL, 'subs' => _ANEWSLETTER, 'active' => _NLACTIVE];
    if ($audit === 'active') return _NLACTIVE.' - '.intval($apar);
    if ($audit === 'list') return _NLSTLIST;
    return $map[$audit] ?? $audit;
}

# Read the hard failure rate each audience produced the last time it was used, so the next choice is made on a result rather than on a rule of thumb
function getCampRates(): array {
    global $db;
    $rows = $db->getSqlRows($db->getSqlQuery('SELECT audit, apar, send, fails FROM '.PREFIX_DB.'_newsletter WHERE send + fails > 0 ORDER BY id ASC')) ?: [];
    $rate = [];
    foreach ($rows as $row) {
        $num = intval($row['send']) + intval($row['fails']);
        if ($num < 1 || (string)$row['audit'] === '') continue;
        $rate[$row['audit'].'-'.$row['apar']] = round(intval($row['fails']) * 100 / $num, 1);
    }
    return $rate;
}

# Build one audience option carrying the recipients it currently resolves to and the failure rate it produced last time
function getCampOption(string $audit, string $apar, string $label, string $pick): array {
    static $rate = null;
    $rate ??= getCampRates();
    $key = $audit.'-'.$apar;
    $text = $label.' - '.getMailAudienceNum($audit, $apar);
    if (isset($rate[$key])) $text .= ' ('._NLRATE.' '.$rate[$key].'%)';
    return ['value_attr' => $key, 'label_text' => $text, 'is_selected' => $key === $pick];
}

# Build the tab strip every screen of this module shows, with only its own index and the filter of a list changed; the three lists redraw in place
function getCampTabs(int $tab, string $find = ''): string {
    $ops = ['name=newsletter', 'name=newsletter&op=add', 'name=newsletter&op=queue', 'name=newsletter&op=dead', 'name=newsletter&op=config', 'name=newsletter&op=info'];
    return getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _ADD, _NLQUEUE, _MAIL_DEAD, _PREFERENCES, _MANUAL], 'tab' => $tab,
        'part' => in_array($tab, [0, 2, 3], true) ? [0, 2, 3] : [], 'subtitle_html' => $find]);
}

# Offer every audience the installation can address as a criterion, never as a list of addresses
# The activity window is one more option and deliberately not the default: only the site owner knows whether dormant accounts are dead weight or their customers
# The window option carries the days of the form, so its value, its count and the row that shows the days field all name the same window
function getCampOptions(string $pick, int $days): array {
    global $db;
    $opts = [getCampOption('all', '', _MASSMAIL, $pick), getCampOption('subs', '', _ANEWSLETTER, $pick), getCampOption('active', (string)$days, _NLACTIVE, $pick)];
    $rows = $db->getSqlRows($db->getSqlQuery('SELECT id, name, extra FROM '.PREFIX_DB.'_groups ORDER BY id')) ?: [];
    foreach ($rows as $row) {
        $name = (intval($row['extra']) === 1) ? _SPEC_GROUP : _GROUP;
        $opts[] = getCampOption('group', (string)$row['id'], $name.' "'.$row['name'].'"', $pick);
    }
    return $opts;
}

# The fan of one campaign in the state it is in: release and stop ask first, the edit or clone is a link, the removal of a drained one waits ten seconds for Undo
function getCampActions(array $camp, int $left): array {
    global $afile;
    $id = intval($camp['id']);
    $stat = intval($camp['status']);
    $keys = ['name' => 'newsletter', 'id' => $id];
    $acts = [];
    if (in_array($stat, [4, 7], true)) $acts[] = getTplPostAction(['op' => 'release'] + $keys, 'play', _NLFREE, _NLFREE.' "'.$camp['title'].'"?');
    if (in_array($stat, [1, 3, 4, 5], true)) $acts[] = getTplPostAction(['op' => 'stop'] + $keys, 'pause', _NLSTOP, _NLSTOP.' "'.$camp['title'].'"?');
    $acts[] = ['href' => $afile.'.php?name=newsletter&op=add&id='.$id, 'icon_name' => ($stat === 0) ? 'pencil' : 'files', 'title' => ($stat === 0) ? _FULLEDIT : _NLCLONE];
    if ($left < 1) $acts[] = getTplPostAction(['op' => 'delete'] + $keys, 'trash', _ONDELETE) + ['later' => 'gone del'];
    return $acts;
}

function newsletter(): void {
    global $db, $conf, $tpl;
    $note = (intval($conf['scheduler']['jobs']['newsletter']['active'] ?? 0) !== 1) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NLNOJOB]) : '';
    $left = [];
    $rows = $db->getSqlRows($db->getSqlQuery('SELECT ref, COUNT(id) AS num FROM '.PREFIX_DB.'_mail WHERE kind = \'newsletter\' AND status = 0 GROUP BY ref')) ?: [];
    foreach ($rows as $row) $left[intval($row['ref'])] = intval($row['num']);
    $sql = 'SELECT id, title, audit, apar, send, fails, total, expect, status, note, time, endtime FROM '.PREFIX_DB.'_newsletter ORDER BY id';
    $list = [];
    foreach ($db->getSqlRows($db->getSqlQuery($sql)) ?: [] as $camp) {
        $id = intval($camp['id']);
        $num = intval($camp['send']) + intval($camp['fails']);
        $rate = ($num > 0) ? round(intval($camp['fails']) * 100 / $num, 1) : 0;
        $span = ($camp['endtime'] > $camp['time']) ? strtotime((string)$camp['endtime']) - strtotime((string)$camp['time']) : 0;
        $list[] = ['name' => (string)$camp['title'], 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $id],
            ['is_truncate' => true, 'title_text' => (string)$camp['title'], 'content_html' => $tpl->getHtmlFrag('popover', [
                'items' => [
                    ['label' => _DATE, 'value' => format_time((string)$camp['time'], _TIMESTRING), 'is_last' => false],
                    ['label' => _TIMENL, 'value' => getDuration($span), 'is_last' => ((string)$camp['note'] === '')],
                    ['label' => _NLNOTE, 'value' => (string)$camp['note'], 'is_last' => true],
                ],
                'label_text' => (string)$camp['title'],
                'title_text' => (string)$camp['title'],
            ])],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => getCampAudit((string)$camp['audit'], (string)$camp['apar'])],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $camp['total'].' / '.$camp['expect']],
            ['class_name' => 'sl-col-sent', 'is_col_count' => true, 'content_html' => $camp['send'].' '._NLUSER],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $camp['fails'].' ('.$rate.'%)'],
            ['is_col_status' => true, 'has_content_text' => true, 'content_text' => getCampState(intval($camp['status']))],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => getCampActions($camp, $left[$id] ?? 0)])],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getCampTabs(0),
        'note_html' => $note,
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TITLE, 'is_truncate' => true],
            ['content' => _NLWHERE, 'is_truncate' => true],
            ['content' => _NLPROGRESS, 'is_col_count' => true],
            ['content' => _NLEND, 'class_name' => 'sl-col-sent', 'is_col_count' => true],
            ['content' => _NLFAILS, 'is_col_count' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $list,
        'count' => count($list),
    ]);
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    $nid = getVar('post', 'nid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $body = getVar('post', 'body', 'text', $conf['mtemp']);
    $pick = getVar('post', 'audit', 'var', 'subs-');
    $days = getVar('post', 'days', 'num', 365);
    $lock = false;
    if ($id) {
        $camp = $db->getSqlRow($db->getSqlQuery('SELECT title, body, audit, apar, status FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]));
        if ($camp) {
            $title = (string)$camp['title'];
            $body = (string)$camp['body'];
            $pick = $camp['audit'].'-'.$camp['apar'];
            if ((string)$camp['audit'] === 'active') $days = intval($camp['apar']);
            $lock = intval($camp['status']) !== 0;
            $nid = $lock ? 0 : $id;
        }
    }
    if (str_starts_with($pick, 'active-')) $pick = 'active-'.$days;
    $stoptext = is_array($stop) ? implode("\n", $stop) : (string)$stop;
    setHead();
    $cont = getCampTabs(1);
    if ($stoptext !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stoptext]);
    if ($lock) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NLLOCKED]);
    if ($body) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $body, 'mod' => 'all']);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'nid', 'valueattr' => (string)$nid],
            ['nameattr' => 'name', 'valueattr' => 'newsletter'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'posttype', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('newsletter')],
        ],
        'rows' => [
            ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'input_id' => 'f-title',
                'value_attr' => $title,
                'maxlength_num' => 50,
                'placeholder_text' => _TITLE,
                'is_required' => true,
            ])],
            [
                'label_for' => 'f-audit',
                'label_html' => _NLWHERE,
                'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'audit', 'selectid' => 'f-audit', 'options' => getCampOptions($pick, $days)]),
            ],
            [
                'label_for' => 'f-days',
                'label_html' => _NLDAYS,
                'hint_html' => _NLDAYSI,
                'hint_id' => $hntid = getFieldIds('f-days')['hint'],
                'field_html' => $tpl->getHtmlFrag('input', [
                    'describedby' => $hntid,
                    'itype' => 'number',
                    'name_attr' => 'days',
                    'input_id' => 'f-days',
                    'value_attr' => (string)$days,
                    'is_config' => true,
                ]),
                'attr' => 'data-sl-show-when="audit" data-sl-show-value="active-'.$days.'"',
            ],
            [
                'label_html' => _TEXT,
                'label_id' => $labid = getFieldIds('', 'body')['label'],
                'field_html' => getTplTextarea([
                    'labelledby' => $labid,
                    'label' => _TEXT,
                    'id' => '1',
                    'name' => 'body',
                    'value' => $body,
                    'mod' => 'all',
                    'store' => 'newsletter.body',
                    'rows' => '10',
                    'placeholder' => _TEXT,
                    'required' => '1',
                ]),
                'is_full' => true,
                'field_unwrapped' => true,
            ],
        ],
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

# Store the criterion of one mailing and hand it to the producer, which is what starts it: nothing is expanded inside this request
# A campaign that has left draft is frozen, delivered rows included, because its body is what the queue still refers to and rewriting it would rewrite the record of what was sent
function save(): void {
    global $db, $afile, $stop;
    $stop = [];
    $nid = getVar('post', 'nid', 'num', 0);
    $title = filterCutText(getVar('post', 'title', 'title'), 50);
    $body = getVar('post', 'body', 'text');
    $pick = getVar('post', 'audit', 'var', '');
    $days = max(1, getVar('post', 'days', 'num', 365));
    $part = explode('-', $pick, 2);
    $audit = $part[0] ?? '';
    $apar = match ($audit) {
        'active' => (string)$days,
        'group' => (string)intval($part[1] ?? 0),
        default => '',
    };
    $warn = !checkAdminPost('newsletter');
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if ($room = checkEditorTextRoom($body, 'newsletter.body')) $stop[] = $room;
    if (!getMailAudience($audit, $apar)) $stop[] = _NLNOAUDIT;
    if (!$warn && !$stop && getVar('post', 'posttype') == 'save') {
        $pars = ['title' => $title, 'body' => $body, 'audit' => $audit, 'apar' => $apar, 'num' => getMailAudienceNum($audit, $apar)];
        if ($nid) {
            $sql = 'UPDATE '.PREFIX_DB.'_newsletter SET title = :title, body = :body, audit = :audit, apar = :apar, expect = :num, status = 1,'
                .' `cursor` = 0, total = 0, send = 0, fails = 0, note = \'\', time = NOW(), endtime = NULL WHERE id = :id AND status = 0';
            $db->getSqlQuery($sql, $pars + ['id' => $nid]);
        } else {
            $sql = 'INSERT INTO '.PREFIX_DB.'_newsletter (title, body, audit, apar, expect, status, time) VALUES (:title, :body, :audit, :apar, :num, 1, NOW())';
            $db->getSqlQuery($sql, $pars);
        }
        setRedirect($afile.'.php?name=newsletter', false, 302, _SUCCSAVE);
    } elseif ($warn) {
        setRedirect($afile.'.php?name=newsletter&op=add'.($nid ? '&id='.$nid : ''), false, 302, _TOKENMISS, true);
    } else {
        add();
    }
}

# Remove a campaign only while nothing in the queue still points at its body, because those rows would then be refused one by one for a reason nobody caused
function delete(): void {
    global $db, $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('newsletter');
    $busy = !$warn && $id && $mailer->getCampLeft($id) > 0;
    $res = (!$warn && $id && !$busy) ? $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $id]) : false;
    $done = $res && $res->rowCount() > 0;
    setRedirect($afile.'.php?name=newsletter', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : ($busy ? _NLBUSY : _ERROR)), !$done);
}

# Release the audience a sample or an abort left holding, which is the transition no measured rate is allowed to perform on its own
function release(): void {
    global $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('newsletter');
    if (!$warn && $id) $mailer->setCampFree($id);
    setRedirect($afile.'.php?name=newsletter', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

# Hold back everything a mailing has not sent yet, on the operator's decision rather than on a measurement
function stop(): void {
    global $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('newsletter');
    if (!$warn && $id) $mailer->setCampAbort($id, _NLSTOPPED);
    setRedirect($afile.'.php?name=newsletter', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

# Warn when the queue has nobody to drain it, because an installation must not be able to accumulate mail silently
# A job that is off says so plainly; a job that is on but has not run within three of its own intervals is stale, which looks the same from the queue
function getQueueAlarm(): string {
    global $conf, $tpl;
    $job = $conf['scheduler']['jobs']['maildrain'] ?? [];
    if (intval($job['active'] ?? 0) !== 1) return $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _MAIL_NODRAIN]);
    $last = intval(getSchedulerState('maildrain')['last_run'] ?? 0);
    $wait = max(300, intval($job['lock_timeout'] ?? 0) ?: 900);
    if ($last > 0 && (time() - $last) < $wait) return '';
    $text = ($last > 0) ? _MAIL_STALE.' '.format_time(date('Y-m-d H:i:s', $last), _TIMESTRING) : _MAIL_STALE;
    return $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $text]);
}

# Render the filter bar of the queue, whose selected state is what the list, the count and the pager all read
function getQueueSearch(string $kind, string $stat): string {
    global $afile, $tpl;
    $kinds = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $kind === '']];
    foreach (getQueueKinds() as $name) $kinds[] = ['value_attr' => $name, 'label_text' => $name, 'is_selected' => $name === $kind];
    $stats = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $stat === '']];
    foreach ([_MAIL_PEND, _MAIL_ACCEPT, _MAIL_FAILED] as $num => $label) {
        $stats[] = ['value_attr' => (string)$num, 'label_text' => $label, 'is_selected' => $stat === (string)$num];
    }
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'newsletter'], ['nameattr' => 'op', 'valueattr' => 'queue']],
        'fields' => [
            ['field_id' => 'sl-queue-kind', 'label' => _MODUL, 'name_attr' => 'kind', 'options' => $kinds],
            ['field_id' => 'sl-queue-stat', 'label' => _STATUS, 'name_attr' => 'status', 'options' => $stats],
        ],
    ]);
}

# List the kinds the queue actually holds, so the filter offers what is there rather than a table of names kept by hand
function getQueueKinds(): array {
    global $db;
    $rows = $db->getSqlRows($db->getSqlQuery('SELECT kind FROM '.PREFIX_DB.'_mail GROUP BY kind ORDER BY kind')) ?: [];
    $out = [];
    foreach ($rows as $row) {
        if ((string)$row['kind'] !== '') $out[] = (string)$row['kind'];
    }
    return $out;
}

# The fan of one queue row: a retry of a failed one reloads the list, a removal waits ten seconds for Undo
function getQueueActions(array $row, string $back): array {
    $keys = ['name' => 'newsletter', 'id' => $row['id'], 'back' => $back];
    $acts = (intval($row['status']) === 2) ? [getTplPostAction(['op' => 'requeue'] + $keys, 'arrow-clockwise', _MAIL_RETRY)] : [];
    $acts[] = getTplPostAction(['op' => 'drop'] + $keys, 'trash', _ONDELETE) + ['later' => 'gone del'];
    return $acts;
}

# Show what the queue holds and what happened to it, in the vocabulary the system can stand behind: accepted, failed, pending, never delivered
function queue(): void {
    global $afile, $mailer, $tpl;
    $kind = getVar('get', 'kind', 'var');
    $stat = trim(getVar('get', 'status', 'raw', ''));
    if (!in_array($stat, ['0', '1', '2'], true)) $stat = '';
    $data = $mailer->getList(['kind' => $kind, 'status' => $stat, 'page' => getVar('get', 'num', 'num', 1)]);
    $back = 'kind='.rawurlencode($kind).'&status='.$stat.'&num='.$data['page'];
    $tally = $mailer->getStats();
    $items = [];
    foreach ([_MAIL_PEND => 'pend', _MAIL_HELD => 'hold', _MAIL_ACCEPT => 'sent', _MAIL_FAILED => 'fail'] as $label => $key) {
        $items[] = ['label_html' => $label, 'field_text' => (string)$tally[$key], 'has_field_text' => true];
    }
    $rows = [];
    foreach ($data['rows'] as $row) {
        $note = trim((string)$row['phase'].' '.$row['code'].' '.$row['error']);
        $rows[] = ['name' => '#'.$row['id'], 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $row['id']],
            ['has_content_text' => true, 'content_text' => (string)$row['kind']],
            ['is_truncate' => true, 'title_text' => (string)$row['email'], 'has_content_text' => true, 'content_text' => (string)$row['email']],
            ['is_truncate' => true, 'title_text' => (string)$row['title'], 'has_content_text' => true, 'content_text' => (string)$row['title']],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)$row['tries']],
            ['is_col_date' => true, 'stamp' => format_time((string)$row['time'], 'c'), 'content_text' => format_time((string)$row['time'], _TIMESTRING)],
            ['is_col_status' => true, 'has_content_text' => true, 'content_text' => getQueueState($row)],
            ['is_truncate' => true, 'title_text' => $note, 'has_content_text' => true, 'content_text' => $note],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => getQueueActions($row, $back)])],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getCampTabs(2, getQueueSearch($kind, $stat)),
        'key' => 'newsletter-queue',
        'note_html' => getQueueAlarm().$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', ['rows' => $items])]),
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _MODUL],
            ['content' => _EMAIL, 'is_truncate' => true],
            ['content' => _TITLE, 'is_truncate' => true],
            ['content' => _MAIL_TRIES, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _ERROR, 'is_truncate' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?name=newsletter&op=queue&kind='.rawurlencode($kind).'&status='.$stat.'&',
        'page' => $data['page'],
        'pages' => $data['pages'],
        'count' => $data['total'],
        'limit' => $data['limit'],
    ]);
    setFoot();
}

# Name the state of one queue row, keeping a held campaign row apart from one the drain simply has not reached yet
function getQueueState(array $row): string {
    $stat = intval($row['status']);
    if ($stat === 1) return _MAIL_ACCEPT;
    if ($stat === 2) return _MAIL_FAILED;
    return (intval($row['hold']) === 1) ? _MAIL_HELD : _MAIL_PEND;
}

# Make one failed row due again after whatever refused it has been fixed
function requeue(): void {
    global $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $back = getVar('post', 'back', 'raw');
    $warn = !checkAdminPost('newsletter');
    if (!$warn && $id) $mailer->setQueueRetry([$id]);
    setRedirect($afile.'.php?name=newsletter&op=queue&'.getQueueQuery($back), false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

# Remove one row from the queue, which is the only way out of it other than delivery or pruning
function drop(): void {
    global $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $back = getVar('post', 'back', 'raw');
    $warn = !checkAdminPost('newsletter');
    $done = !$warn && $id && $mailer->deleteQueueRows([$id]) > 0;
    setRedirect($afile.'.php?name=newsletter&op=queue&'.getQueueQuery($back), false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

# Rebuild the filter state of the list an action was started from, taking only the three keys it may carry
function getQueueQuery(string $back): string {
    $vars = [];
    parse_str($back, $vars);
    $kind = filterVar((string)($vars['kind'] ?? ''));
    $stat = (string)intval($vars['status'] ?? 0);
    if (!in_array((string)($vars['status'] ?? ''), ['0', '1', '2'], true)) $stat = '';
    return 'kind='.rawurlencode($kind).'&status='.$stat.'&num='.max(1, intval($vars['num'] ?? 1));
}

function dead(): void {
    global $afile, $mailer, $tpl;
    $data = $mailer->getDeadList(getVar('get', 'num', 'num', 1));
    $rows = [];
    foreach ($data['rows'] as $row) {
        $note = trim((string)$row['phase'].' '.$row['code']);
        $rows[] = ['name' => (string)$row['email'], 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $row['id']],
            ['is_truncate' => true, 'title_text' => (string)$row['email'], 'has_content_text' => true, 'content_text' => (string)$row['email']],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)$row['fails']],
            ['is_truncate' => true, 'title_text' => $note, 'has_content_text' => true, 'content_text' => $note],
            ['is_col_date' => true, 'stamp' => format_time((string)$row['time'], 'c'), 'content_text' => format_time((string)$row['time'], _TIMESTRING)],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => [getTplPostAction(['name' => 'newsletter',
                'op' => 'revive', 'id' => $row['id'], 'num' => $data['page']], 'arrow-counterclockwise', _MAIL_REVIVE) + ['later' => 'gone move']]])],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getCampTabs(3),
        'key' => 'newsletter-dead',
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _EMAIL, 'is_truncate' => true],
            ['content' => _MAIL_FAILS, 'is_col_count' => true],
            ['content' => _ERROR, 'is_truncate' => true, 'nosort' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?name=newsletter&op=dead&',
        'page' => $data['page'],
        'pages' => $data['pages'],
        'count' => $data['total'],
        'limit' => $data['limit'],
    ]);
    setFoot();
}

function revive(): void {
    global $afile, $mailer;
    $id = getVar('post', 'id', 'num');
    $page = getVar('post', 'num', 'num', 1);
    $warn = !checkAdminPost('newsletter');
    $done = !$warn && $id && $mailer->deleteDeadMail([$id]) > 0;
    setRedirect($afile.'.php?name=newsletter&op=dead&num='.$page, false, 302, $warn ? _TOKENMISS : ($done ? _SUCCSAVE : _ERROR), !$done);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getCampTabs(4);
    $cont .= checkPerms(CONFIG_DIR.'/newsletter.php');
    $rule = is_array($conf['newsletter'] ?? null) ? $conf['newsletter'] : [];
    $rows = [];
    foreach ([['canary', _NLCANARY, _NLCANARYI, '100'], ['canarymin', _NLCANMIN, _NLCANMINI, '500'], ['breakwin', _NLBREAK, _NLBREAKI, '100'],
        ['abort', _NLABORT, _NLABORTI, '10'], ['bouncemax', _NLBOUNCE, _NLBOUNCEI, '2']] as $item) {
        $rows[] = [
            'label_html' => $item[1],
            'hint_html' => $item[2],
            'hint_id' => $hntid = getFieldIds('', $item[0])['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => $item[0],
                'value_attr' => (string)($rule[$item[0]] ?? $item[3]),
                'is_config' => true,
            ]),
        ];
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'newsletter'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('newsletter')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

# Store campaign policy, validating each field rather than trusting it: a window of zero divides by zero and a bounce cap of zero suppresses every address on its first result
function configsave(): void {
    global $afile;
    $warn = !checkAdminPost('newsletter');
    if (!$warn) {
        $content = [
            'abort' => max(1, min(100, getVar('post', 'abort', 'num', 10))),
            'bouncemax' => max(1, getVar('post', 'bouncemax', 'num', 2)),
            'breakwin' => max(1, getVar('post', 'breakwin', 'num', 100)),
            'canary' => max(0, getVar('post', 'canary', 'num', 100)),
            'canarymin' => max(0, getVar('post', 'canarymin', 'num', 500)),
        ];
        setConfigFile('newsletter.php', $content);
    }
    setRedirect($afile.'.php?name=newsletter&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=newsletter', 'name=newsletter&op=add', 'name=newsletter&op=queue', 'name=newsletter&op=dead', 'name=newsletter&op=config', 'name=newsletter&op=info'],
        'tabs' => [_HOME, _ADD, _NLQUEUE, _MAIL_DEAD, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: newsletter(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'release': release(); break;
    case 'stop': stop(); break;
    case 'queue': queue(); break;
    case 'requeue': requeue(); break;
    case 'drop': drop(); break;
    case 'dead': dead(); break;
    case 'revive': revive(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
