<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

require_once BASE_DIR.'/core/classes/update.php';

# The tabs of the updates in their order; the journal link is added by getTplAdminTabs()
const UPDATE_TABS = ['_HOME', '_PREFERENCES'];

# The header of the updates with its tabs and the manual
function getUpdateHead(int $tab): string {
    $ops = array_map(static fn(int $i): string => 'name=update&tab='.$i, array_keys(UPDATE_TABS));
    return getTplAdminTabs(['ops' => array_merge($ops, ['name=update&op=info']), 'tabs' => array_merge(array_map('constant', UPDATE_TABS), [_MANUAL]), 'tab' => $tab]);
}

# The tab of the overview: the version and its channel, the last check and the release found, how this site verifies and unpacks, and its id for support
function getUpdateView(): string {
    global $conf, $tpl;
    $state = getUpdateSite();
    $sets = $conf['update']['check'] ?? [];
    $cont = getUpdateAlert();
    if (intval($sets['upcheck'] ?? 1) !== 1) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _UPDATE_OFF]);
    $last = (intval($state['last']) > 0) ? date(_TIMESTRING, intval($state['last'])) : _NEVER;
    if ($state['error'] !== '') $last .= ' - '.sprintf(_UPDATE_REFUSE, $state['error']);
    $bits = Update::getExtBits();
    $list = [
        [_VERSION, SL_VERSION.' '.SL_CODENAME],
        [_UPDATE_CHANNEL, (($sets['channel'] ?? '') === 'beta') ? _UPDATE_BETA : _UPDATE_STABLE],
        [_UPDATE_LAST, $last],
        [_UPDATE_FOUND, Update::isVersion($state['found']['version'] ?? null) ? $state['found']['version'] : _UPDATE_NONE],
        [_UPDATE_SIGN, ($bits & 1) ? _UPDATE_SODIUM : _UPDATE_OWN],
        [_UPDATE_UNZIP, ($bits & 2) ? 'ZipArchive' : (($bits & 4) ? 'PharData' : _UPDATE_NOZIP)],
    ];
    $items = [];
    foreach ($list as [$label, $text]) $items[] = ['label' => $label, 'text' => $text];
    $hid = getFieldIds('', 'sid')['hint'];
    $sid = $tpl->getHtmlFrag('code-block', ['lang_text' => 'sid', 'label_text' => _UPDATE_SID, 'copy_text' => _COPY, 'done_text' => _COPYDONE,
        'describedby' => $hid, 'content_html' => htmlspecialchars((string)$state['sid'], ENT_QUOTES, 'UTF-8')]);
    $rows = [['label_html' => _UPDATE_SID, 'hint_html' => _UPDATE_SIDHINT, 'hint_id' => $hid, 'field_html' => $sid]];
    $last = getUpdateLast($state);
    $cont .= $last['html'];
    $pack = getUpdatePack($state, (($sets['channel'] ?? '') === 'beta') ? 'beta' : 'stable');
    $acts = getTplPostButton(['name' => 'update', 'op' => 'check'], 'arrow-repeat', _UPDATE_CHECK).$pack['acts']
        .getTplPostButton(['name' => 'update', 'op' => 'audit'], 'shield-check', _UPDATE_AUDIT);
    $rows = array_merge($rows, $last['rows'], [['label_html' => _FUNCTIONS, 'field_html' => $acts]]);
    $list = $tpl->getHtmlFrag('kv-list', ['items' => array_merge($items, $last['items'], $pack['items'])]);
    return $cont.$tpl->getHtmlFrag('pane', ['title' => _UPDATE_TITLE, 'icon' => 'arrow-repeat', 'meta' => SL_VERSION,
        'content_html' => $pack['bars'].$list.$tpl->getHtmlPart('div', ['rows' => $rows])]).$pack['html'];
}

# The meter of the space a step needs, or its words alone where the free space cannot be read; what the step may remove counts as free, as its own check does
function getUpdateRoom(array $room, string $text, int $old = 0): string {
    global $tpl;
    if ($room['free'] === null) return $tpl->getHtmlFrag('kv-list', ['items' => [['label' => _UPDATE_SPACE, 'text' => $text]]]);
    return getTplMeter(_UPDATE_SPACE, $room['need'], $room['free'] + $old, $text);
}

# One list under the overview: its heading with the count and at most 100 entries, the rest counted
function getUpdateList(string $head, array $items, bool $warn = true): string {
    global $tpl;
    $items = array_map('strval', $items);
    $more = (count($items) > 100) ? array_merge(array_slice($items, 0, 100), [sprintf(_UPDATE_MORE, count($items) - 100)]) : $items;
    return $tpl->getHtmlFrag('alert', ['is_warn' => $warn, 'text' => sprintf($head, count($items)), 'messages' => $more]);
}

# The last installation and the last rollback: the self-check of a fresh installation runs once here, and its answer offers going back or confirming the site by hand
function getUpdateLast(array $state): array {
    global $conf, $tpl;
    $out = ['items' => [], 'rows' => [], 'html' => ''];
    $done = (array)$state['done'];
    $undo = (array)$state['undo'];
    if (Update::isVersion($undo['to'] ?? null)) {
        $cause = (string)$undo['why'].(((string)$undo['error'] !== '') ? ': '.$undo['error'] : '');
        $why = ($undo['why'] === 'revert') ? _UPDATE_UNDOREV : sprintf(_UPDATE_UNDOFAIL, intval($undo['step']), $cause);
        $out['items'][] = ['label' => _UPDATE_UNDO, 'text' => sprintf(_UPDATE_RUN, $undo['from'], $undo['to'], date(_TIMESTRING, intval($undo['time']))).' - '.$why];
    }
    if (!Update::isVersion($done['to'] ?? null)) return $out;
    if (($done['check'] ?? '') === 'wait') {
        $res = Update::getSelfCheck((string)($conf['homeurl'] ?? ''));
        $done['check'] = $res['check'];
        $done['code'] = $res['code'];
        Update::setState(array_replace(Update::getState(), ['done' => $done]));
    }
    $back = ($done['back'] ?? '') !== '' && is_dir(BASE_DIR.'/'.$done['back']);
    $text = sprintf(_UPDATE_RUN, $done['from'], $done['to'], date(_TIMESTRING, intval($done['time'])));
    $out['items'][] = ['label' => _UPDATE_LASTRUN, 'text' => $text.' - '.match ($done['check']) {
        'ok' => _UPDATE_SELFOK,
        'down' => sprintf(_UPDATE_SELFBAD, intval($done['code'] ?? 0)),
        default => _UPDATE_SELFNONE,
    }];
    $acts = ($done['check'] === 'none') ? getTplPostButton(['name' => 'update', 'op' => 'selfok'], 'check2-circle', _UPDATE_ALLOK) : '';
    if ($back) $acts .= getTplPostButton(['name' => 'update', 'op' => 'revert'], 'arrow-counterclockwise', _UPDATE_REVERT);
    if ($acts !== '') $out['rows'][] = ['label_html' => _UPDATE_LASTRUN, 'field_html' => $acts];
    if ($done['check'] === 'down') $out['html'] = $tpl->getHtmlFrag('alert', ['type' => 'error', 'text' => _UPDATE_DOWN]);
    return $out;
}

# The box of the installation under a ready package: the space step 3 needs, the marks the preflight asks for and the button, closed while the space is short
function getUpdateInstall(array $state, array $man): string {
    global $afile, $db, $tpl;
    require_once BASE_DIR.'/core/classes/backup.php';
    $dir = UPDATE_DIR.'/client/stage/'.$man['version'];
    $last = (string)($state['done']['back'] ?? '');
    try {
        $dump = intdiv(Update::getBaseSize($db->sqlconnid, Backup::getSiteTables($db->sqlconnid, PREFIX_DB)) * 6, 5);
    } catch (Throwable) {
        $dump = 0;
    }
    $room = Update::getApplySpace($dump, ['bsize' => 0, 'nsize' => 0], $dir, $last);
    if (!$room['same']) $room = Update::getApplySpace($dump, Update::getApplyPlan($dir, (string)$afile), $dir, $last);
    $text = ($room['free'] === null) ? sprintf(_UPDATE_ROOMNO, filterSize($room['need'])) : sprintf(_UPDATE_ROOM, filterSize($room['need']), filterSize($room['free']));
    if ($room['old'] > 0) $text .= ' '.sprintf(_UPDATE_ROOMBAK, filterSize($room['old']));
    $short = !$room['ok'] && $room['free'] !== null;
    if ($short) $text .= ' '.sprintf(_UPDATE_ROOMLOW, filterSize($room['need'] - $room['free'] - $room['old']));
    $rows = [];
    $marks = [];
    if (!empty($state['stage']['changed'])) $marks[] = ['over', _UPDATE_OVER];
    if ($room['free'] === null) $marks[] = ['free', _UPDATE_FREEOK];
    foreach ($marks as [$key, $label]) {
        $rows[] = ['label_html' => _CONFIRM, 'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => $key, 'value_attr' => '1', 'label_text' => $label,
            'is_required' => true])];
    }
    return $tpl->getHtmlFrag('pane', ['title' => _UPDATE_INSTALL, 'icon' => 'box-arrow-in-down', 'content_html' => getUpdateRoom($room, $text, $room['old'])
        .$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPDATE_INSTHINT])
        .$tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=update&op=install',
            'hidden' => [['name_attr' => 'token', 'value_attr' => getSiteToken('update')]],
            'rows' => $rows,
            'submit_label' => _UPDATE_INSTALL,
            'is_disabled' => $short,
        ])]);
}

# The package of the release found: the archive this site takes and the space it needs, a download cut midway, the unpacked package, the last refusal and integrity check
function getUpdatePack(array $state, string $chan): array {
    global $afile, $tpl;
    $out = ['items' => [], 'bars' => '', 'acts' => '', 'html' => ''];
    $line = static fn(string $label, string $text): array => ['label' => $label, 'text' => $text];
    $man = Update::getOffer($state, $chan);
    $reader = Update::getZipReader();
    $stage = (array)$state['stage'];
    $ready = false;
    if ($man['ok']) {
        $data = $man['data'];
        $kind = Update::getPackKind($data);
        $pack = $data[$kind];
        $name = $data['version'].', '.(($kind === 'delta') ? sprintf(_UPDATE_DELTA, $pack['from']) : _UPDATE_FULL).', '.filterSize($pack['size']);
        $sums = array_column(array_filter([$data['full'], $data['delta']], 'is_array'), 'sha256');
        $ready = ($stage['version'] ?? '') === $data['version'] && in_array($stage['sha256'] ?? '', $sums, true) && is_dir(UPDATE_DIR.'/client/stage/'.$data['version']);
        if ($ready) {
            $out['items'][] = $line(_UPDATE_PACK, $name.' - '.sprintf(_UPDATE_STAGED, intval($stage['files'] ?? 0), date(_TIMESTRING, intval($stage['time'] ?? 0))));
            if (!empty($stage['nolist'])) $out['html'] .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _UPDATE_NOLIST]);
            if (!empty($stage['changed'])) $out['html'] .= getUpdateList(_UPDATE_CHANGED, (array)$stage['changed']);
            $out['acts'] = getTplPostButton(['name' => 'update', 'op' => 'test'], 'clipboard-check', _UPDATE_TEST);
            $out['html'] .= getUpdateInstall($state, $data);
        } else {
            $temp = Update::getTempFile($data['version'], $kind);
            $have = is_file($temp) ? (int)filesize($temp) : 0;
            $room = Update::getSpaceNeed($pack, $have);
            $out['items'][] = $line(_UPDATE_PACK, $name);
            if ($have > 0) $out['bars'] .= getTplMeter(_UPDATE_PACK, $have, $pack['size'], sprintf(_UPDATE_GOT, filterSize($have), filterSize($pack['size'])), 'ok');
            $free = ($room['free'] === null) ? sprintf(_UPDATE_NOFREE, filterSize($room['need'])) : sprintf(_UPDATE_NEED, filterSize($room['need']), filterSize($room['free']));
            $out['bars'] .= getUpdateRoom($room, $free.($room['ok'] ? '' : ' '.sprintf(_UPDATE_LOW, filterSize($room['need'] - intval($room['free'])))));
            if ($reader !== '' && $room['ok']) {
                $out['acts'] = getTplPostButton(['name' => 'update', 'op' => 'download'], 'cloud-download', ($have > 0) ? _UPDATE_GETMORE : _DOWNLOAD);
            }
        }
    }
    $fault = (array)$state['fault'];
    if (!$ready && ($fault['error'] ?? '') !== '') {
        $why = (string)$fault['error'].(((string)($fault['name'] ?? '') !== '') ? ' - '.(string)$fault['name'] : '');
        $out['items'][] = $line(_UPDATE_FAULT, date(_TIMESTRING, intval($fault['time'] ?? 0)).' - '.sprintf(_UPDATE_REJECT, $why));
    }
    $audit = (array)$state['audit'];
    $seen = (intval($audit['time'] ?? 0) > 0) ? date(_TIMESTRING, intval($audit['time'])) : '';
    $dirty = !empty($audit['changed']) || !empty($audit['missing']) || !empty($audit['extra']);
    $text = match (true) {
        $seen === '' => _NEVER,
        ($audit['error'] ?? '') === 'nolist' => $seen.' - '._UPDATE_NOLIST,
        $dirty => sprintf(_UPDATE_DIRTY, $seen),
        default => sprintf(_UPDATE_CLEAN, $seen),
    };
    $out['items'][] = $line(_UPDATE_INTEG, $text);
    foreach (['changed' => _UPDATE_MODIFIED, 'missing' => _UPDATE_MISSING, 'extra' => _UPDATE_EXTRA] as $key => $head) {
        if (!empty($audit[$key])) $out['html'] .= getUpdateList($head, (array)$audit[$key]);
    }
    if ($reader === '' || $ready) return $out;
    $hint = sprintf(_UPDATE_UPHINT, (string)ini_get('upload_max_filesize'), (string)ini_get('post_max_size'));
    $out['html'] .= $tpl->getHtmlPart('box', ['title' => _UPDATE_UPLOAD, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=update&op=upload',
        'form_attr' => 'enctype="multipart/form-data"',
        'hidden' => [['name_attr' => 'token', 'value_attr' => getSiteToken('update')]],
        'rows' => [['label_for' => 'f-file', 'label_html' => _FILE, 'hint_html' => htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'), 'hint_id' => 'f-file-hint',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'file', 'name_attr' => 'file', 'input_id' => 'f-file', 'describedby' => 'f-file-hint',
                'input_attr' => 'accept="application/zip,.zip"', 'is_required' => true])]],
        'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _UPDATE_UPLOAD, 'button_type' => 'submit']),
    ])]);
    return $out;
}

# The flash text of a download or an upload: the part downloaded, the package unpacked, or the refusal with the entry it names
function getUpdateNote(array $res, string $ver): string {
    if (!$res['ok']) return sprintf(_UPDATE_REJECT, htmlspecialchars($res['error'].(($res['name'] !== '') ? ' - '.$res['name'] : ''), ENT_QUOTES, 'UTF-8'));
    if (!empty($res['done']) || !isset($res['done'])) return sprintf(_UPDATE_STAGEOK, htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'));
    return sprintf(_UPDATE_GOT, filterSize($res['got']), filterSize($res['size']));
}

# The tab of settings: the channel, the daily check beside the list of what it sends, the domain and the backup kept for going back
function getUpdateSettings(): string {
    global $afile, $conf, $tpl;
    $sets = $conf['update']['check'] ?? [];
    $back = (string)(Update::getState()['done']['back'] ?? '');
    $radio = static function (string $name, string $label, string $value, array $opts, string $hint = ''): array {
        $ids = getFieldIds('', $name);
        $hid = ($hint !== '') ? $ids['hint'] : '';
        $field = getTplRadioGroup(['labelledby' => $ids['label'], 'describedby' => $hid, 'name' => $name, 'value' => $value, 'options' => $opts]);
        return ['label_html' => $label, 'label_id' => $ids['label'], 'hint_html' => $hint, 'hint_id' => $hid, 'field_html' => $field];
    };
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $sent = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPDATE_SENT, 'messages' => [_UPDATE_S_ID, _UPDATE_S_VER, _UPDATE_S_ENV, _UPDATE_S_ERR, _UPDATE_S_HOST,
        _UPDATE_S_FAIL]]);
    $rows = [
        $radio('channel', _UPDATE_CHANNEL, (($sets['channel'] ?? '') === 'beta') ? 'beta' : 'stable', [['value' => 'stable', 'label' => _UPDATE_STABLE],
            ['value' => 'beta', 'label' => _UPDATE_BETA]]),
        $radio('upcheck', _UPDATE_UPCHECK, (string)intval($sets['upcheck'] ?? 1), $yesno),
        $radio('upsite', _UPDATE_UPSITE, (string)intval($sets['upsite'] ?? 1), $yesno, _UPDATE_SITEHINT),
        $radio('keepbak', _UPDATE_KEEPBAK, (string)intval($sets['keepbak'] ?? 1), $yesno, ($back !== '' && is_dir(BASE_DIR.'/'.$back))
            ? sprintf(_UPDATE_BAKSIZE, filterSize(Update::getDirSize(BASE_DIR.'/'.$back))) : ''),
    ];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=update&op=save',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('update')]],
        'content_html' => $sent.$tpl->getHtmlPart('div', ['rows' => $rows]),
        'submit_label' => _SAVECHANGES,
    ]);
    return checkPerms(CONFIG_DIR.'/update.php').$tpl->getHtmlPart('box', ['content_html' => $form]);
}

function update(): void {
    $tab = getVar('get', 'tab', 'num', 0);
    if ($tab < 0 || $tab >= count(UPDATE_TABS)) $tab = 0;
    setHead();
    echo getUpdateHead($tab).(($tab === 1) ? getUpdateSettings() : getUpdateView());
    setFoot();
}

function save(): void {
    global $afile, $conf;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update&tab=1', false, 302, _TOKENMISS, true);
    $bit = static fn(string $key): string => (getVar('post', $key, 'num', 1) === 1) ? '1' : '0';
    $data = (array)($conf['update'] ?? []);
    $data['check'] = ['upcheck' => $bit('upcheck'), 'channel' => (getVar('post', 'channel', 'var', '') === 'beta') ? 'beta' : 'stable', 'keepbak' => $bit('keepbak'),
        'upsite' => $bit('upsite')];
    $done = setConfigFile('update.php', $data);
    setRedirect($afile.'.php?name=update&tab=1', false, 302, $done ? _SUCCSAVE : _ERROR, !$done);
}

function check(): void {
    global $afile, $conf, $db;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $state = getUpdateSite();
    $res = Update::addCheck($db, $conf, $state);
    $text = $res['ok'] ? (($res['found'] !== '') ? sprintf(_UPDATE_NEW, $res['found']) : _UPDATE_NONE) : sprintf(_UPDATE_REFUSE, $res['error']);
    setRedirect($afile.'.php?name=update', false, 302, $text, !$res['ok']);
}

function download(): void {
    global $afile, $conf;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $man = Update::getOffer(getUpdateSite(), (($conf['update']['check']['channel'] ?? '') === 'beta') ? 'beta' : 'stable');
    $res = $man['ok'] ? Update::addDownload($man['data']) : ['ok' => false, 'error' => $man['error'], 'name' => ''];
    setRedirect($afile.'.php?name=update', false, 302, getUpdateNote($res, (string)($man['data']['version'] ?? '')), !$res['ok']);
}

function upload(): void {
    global $afile, $conf, $db;
    $back = $afile.'.php?name=update';
    if (!isset($_FILES['file']) && intval($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) setRedirect($back, false, 302, sprintf(_UPDATE_REJECT, 'upload'), true);
    if (!checkAdminPost('update')) setRedirect($back, false, 302, _TOKENMISS, true);
    $file = $_FILES['file'] ?? null;
    $temp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    if (intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($temp)) setRedirect($back, false, 302, sprintf(_UPDATE_REJECT, 'upload'), true);
    $chan = (($conf['update']['check']['channel'] ?? '') === 'beta') ? 'beta' : 'stable';
    $chk = Update::addCheck($db, $conf, getUpdateSite());
    $man = $chk['ok'] ? Update::getOffer(Update::getState(), $chan) : ['ok' => false, 'error' => $chk['error'], 'data' => []];
    $res = $man['ok'] ? Update::addUpload($temp, $man['data']) : ['ok' => false, 'error' => $man['error'], 'name' => ''];
    if (is_file($temp)) unlink($temp);
    setRedirect($back, false, 302, getUpdateNote($res, (string)($man['data']['version'] ?? '')), !$res['ok']);
}

function audit(): void {
    global $afile;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $res = Update::addAudit(BASE_DIR, (string)$afile);
    $bad = $res['error'] !== '' || $res['changed'] !== [] || $res['missing'] !== [] || $res['extra'] !== [];
    $text = ($res['error'] !== '') ? (($res['error'] === 'nolist') ? _UPDATE_NOLIST : _ERROR) : sprintf($bad ? _UPDATE_DIRTY : _UPDATE_CLEAN, date(_TIMESTRING, $res['time']));
    setRedirect($afile.'.php?name=update', false, 302, $text, $bad);
}

function hide(): void {
    global $afile;
    $done = checkAdminPost('update') && Update::setNoteHide(getVar('post', 'id', 'num', 0));
    setRedirect($afile.'.php?name=update', true, 302, $done ? _SUCCSAVE : _ERROR, !$done);
}

# The preflight of the release found over its unpacked package; a manifest the keys in force no longer verify is the refusal offer
function getUpdateCheck(array $opts = []): array {
    global $afile, $conf, $db;
    $state = Update::getState();
    $man = Update::getOffer($state, (($conf['update']['check']['channel'] ?? '') === 'beta') ? 'beta' : 'stable');
    if (!$man['ok']) return ['ok' => false, 'error' => $man['error'], 'name' => '', 'man' => []];
    return Update::getApplyCheck($db->sqlconnid, PREFIX_DB, $state, $man['data'], (string)$afile, $opts) + ['man' => $man['data']];
}

# The refusal of a preflight as the flash text of the overview
function getUpdateDeny(array $res): string {
    return sprintf(_UPDATE_DENY, htmlspecialchars($res['error'].(($res['name'] !== '') ? ' - '.$res['name'] : ''), ENT_QUOTES, 'UTF-8'));
}

# The page of the rescue code, shown once before the site closes: the code, what it is for, and the form that starts the installation or the rollback with its token
function setUpdateCode(string $title, string $lead, string $op, string $button, array $codes, bool $danger = false): void {
    global $afile, $tpl;
    $code = implode('-', str_split($codes['code'], 4));
    $hid = getFieldIds('', 'code')['hint'];
    $block = $tpl->getHtmlFrag('code-block', ['lang_text' => 'code', 'label_text' => _UPDATE_CODE, 'copy_text' => _COPY, 'done_text' => _COPYDONE,
        'describedby' => $hid, 'content_html' => htmlspecialchars($code, ENT_QUOTES, 'UTF-8')]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => array_map(static fn(string $key, string $val): array => ['name_attr' => $key, 'value_attr' => $val], ['name', 'op', 'token', 'apply'],
            ['update', $op, getSiteToken('update'), $codes['token']]),
        'rows' => [['label_html' => _UPDATE_CODE, 'hint_html' => _UPDATE_CODEHINT, 'hint_id' => $hid, 'field_html' => $block]],
        'submit_label' => $button,
        'is_danger' => $danger,
    ]);
    setHead();
    echo getUpdateHead(0).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $lead]).$tpl->getHtmlPart('box', ['title' => $title, 'content_html' => $form]);
    setFoot();
}

# The pending installation or rollback a start carries: its kind, version and the token of the code page, not older than WAIT, and taken from the state at once
function getUpdatePending(string $kind): array {
    $state = Update::getState();
    $pend = (array)$state['apply'];
    $tok = getVar('post', 'apply', 'var', '');
    $good = ($pend['kind'] ?? '') === $kind && is_string($pend['token'] ?? null) && strlen($tok) === 64 && hash_equals($pend['token'], hash('sha256', $tok))
        && intval($pend['time'] ?? 0) > time() - Update::WAIT;
    if (!$good) return [];
    Update::setState(array_replace($state, ['apply' => []]));
    return $pend;
}

# The report of the dry run: what the installation replaces, adds and deletes, the changed files, the options of config/, the database steps and the space
function test(): void {
    global $afile, $tpl;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $res = getUpdateCheck(['over' => true, 'free' => true]);
    $cont = $tpl->getHtmlFrag('alert', $res['ok'] ? ['type' => 'success', 'text' => _UPDATE_READY] : ['type' => 'error', 'text' => getUpdateDeny($res)]);
    if (($res['plan'] ?? []) !== []) {
        $room = $res['room'];
        $text = ($room['free'] === null) ? sprintf(_UPDATE_ROOMNO, filterSize($room['need'])) : sprintf(_UPDATE_ROOM, filterSize($room['need']), filterSize($room['free']));
        if ($room['old'] > 0) $text .= ' '.sprintf(_UPDATE_ROOMBAK, filterSize($room['old']));
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => !$room['ok'], 'text' => _UPDATE_SPACE.': '.$text]);
        $opts = [];
        foreach ($res['adds'] as $file => $paths) foreach ($paths as $path) $opts[] = 'config/'.$file.': '.$path;
        $steps = [];
        foreach ($res['steps'] as $ver => $has) $steps[] = $ver.': '.(implode(' + ', array_keys(array_filter(['SQL' => $has['sql'], 'PHP' => $has['php']]))) ?: _UPDATE_MARK);
        $lists = [[_UPDATE_PUTS, $res['plan']['swaps']], [_UPDATE_ADDS, $res['plan']['adds']], [_UPDATE_DROPS, $res['plan']['drops']],
            [_UPDATE_CHANGED, $res['plan']['changed']], [_UPDATE_OPTS, $opts], [_UPDATE_RUNS, $steps], [_UPDATE_DENIED, $res['plan']['deny']]];
        foreach ($lists as [$head, $items]) if ($items) $cont .= getUpdateList($head, $items, $head === _UPDATE_CHANGED || $head === _UPDATE_DENIED);
    }
    setHead();
    echo getUpdateHead(0).$tpl->getHtmlPart('box', ['title' => _UPDATE_TEST, 'content_html' => $cont]);
    setFoot();
}

function install(): void {
    global $afile;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $opts = ['over' => getVar('post', 'over', 'num', 0) === 1, 'free' => getVar('post', 'free', 'num', 0) === 1];
    $res = getUpdateCheck($opts);
    $state = Update::getState();
    if (isset($res['plan']['changed']) && $state['stage'] !== []) {
        Update::setState(array_replace($state, ['stage' => array_replace((array)$state['stage'], ['changed' => $res['plan']['changed']])]));
    }
    if (!$res['ok']) setRedirect($afile.'.php?name=update', false, 302, getUpdateDeny($res), true);
    $codes = Update::getApplyCode();
    $pend = ['kind' => 'install', 'ver' => $res['man']['version'], 'token' => hash('sha256', $codes['token']), 'code' => hash('sha256', $codes['code']), 'time' => time()] + $opts;
    if (!Update::setState(array_replace(Update::getState(), ['apply' => $pend]))) setRedirect($afile.'.php?name=update', false, 302, _ERROR, true);
    setUpdateCode(_UPDATE_INSTALL.' '.$res['man']['version'], _UPDATE_STARTHINT, 'start', _UPDATE_START, $codes);
}

function start(): void {
    global $afile;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $pend = getUpdatePending('install');
    if (!$pend) setRedirect($afile.'.php?name=update', false, 302, _UPDATE_EXPIRED, true);
    $res = getUpdateCheck(['over' => !empty($pend['over']), 'free' => !empty($pend['free'])]);
    if (!$res['ok'] || $res['man']['version'] !== $pend['ver']) setRedirect($afile.'.php?name=update', false, 302, getUpdateDeny($res), true);
    $made = Update::addApplyStart($pend, $res, $res['man'], (string)$afile, PREFIX_DB);
    setAdminLog(['result' => $made['ok'] ? 'done' : 'failed', 'ver' => $pend['ver']]);
    if (!$made['ok']) setRedirect($afile.'.php?name=update', false, 302, getUpdateDeny($made + ['name' => '']), true);
    require UPDATE_DIR.'/client/run/apply.php';
}

function revert(): void {
    global $afile;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $done = (array)Update::getState()['done'];
    $deny = getUpdateDeny(['error' => 'back', 'name' => '']);
    if (($done['back'] ?? '') === '' || !is_dir(BASE_DIR.'/'.$done['back'])) setRedirect($afile.'.php?name=update', false, 302, $deny, true);
    $codes = Update::getApplyCode();
    $pend = ['kind' => 'revert', 'ver' => (string)$done['from'], 'token' => hash('sha256', $codes['token']), 'code' => hash('sha256', $codes['code']), 'time' => time()];
    if (!Update::setState(array_replace(Update::getState(), ['apply' => $pend]))) setRedirect($afile.'.php?name=update', false, 302, _ERROR, true);
    setUpdateCode(_UPDATE_REVERT, sprintf(_UPDATE_REVHINT, (string)$done['from']), 'back', _UPDATE_REVGO, $codes, true);
}

function back(): void {
    global $afile;
    if (!checkAdminPost('update')) setRedirect($afile.'.php?name=update', false, 302, _TOKENMISS, true);
    $pend = getUpdatePending('revert');
    if (!$pend) setRedirect($afile.'.php?name=update', false, 302, _UPDATE_EXPIRED, true);
    $made = Update::addApplyRevert($pend, (string)$afile);
    setAdminLog(['result' => $made['ok'] ? 'done' : 'failed', 'ver' => $pend['ver']]);
    if (!$made['ok']) setRedirect($afile.'.php?name=update', false, 302, getUpdateDeny($made + ['name' => '']), true);
    require BASE_DIR.'/core/rescue.php';
}

function selfok(): void {
    global $afile;
    $state = Update::getState();
    $done = checkAdminPost('update') && Update::isVersion($state['done']['to'] ?? null)
        && Update::setState(array_replace($state, ['done' => array_replace((array)$state['done'], ['check' => 'ok'])]));
    setRedirect($afile.'.php?name=update', false, 302, $done ? _SUCCSAVE : _ERROR, !$done);
}

function info(): void {
    $ops = array_map(static fn(int $i): string => 'name=update&tab='.$i, array_keys(UPDATE_TABS));
    setTplAdminInfoPage(['ops' => array_merge($ops, ['name=update&op=info']), 'tabs' => array_merge(array_map('constant', UPDATE_TABS), [_MANUAL])]);
}

switch ($op) {
    default: update(); break;
    case 'save': save(); break;
    case 'check': check(); break;
    case 'download': download(); break;
    case 'upload': upload(); break;
    case 'audit': audit(); break;
    case 'hide': hide(); break;
    case 'test': test(); break;
    case 'install': install(); break;
    case 'start': start(); break;
    case 'revert': revert(); break;
    case 'back': back(); break;
    case 'selfok': selfok(); break;
    case 'info': info(); break;
}
