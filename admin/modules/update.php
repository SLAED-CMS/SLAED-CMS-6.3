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
    $rows = [];
    foreach ($list as [$label, $text]) $rows[] = ['label_html' => $label, 'has_field_text' => true, 'field_text' => $text];
    $sid = $tpl->getHtmlFrag('code-block', ['lang_text' => 'sid', 'label_text' => _UPDATE_SID, 'copy_text' => _COPY, 'done_text' => _COPYDONE,
        'content_html' => htmlspecialchars((string)$state['sid'], ENT_QUOTES, 'UTF-8')]);
    $rows[] = ['label_html' => _UPDATE_SID, 'hint_html' => _UPDATE_SIDHINT, 'field_html' => $sid];
    $pack = getUpdatePack($state, (($sets['channel'] ?? '') === 'beta') ? 'beta' : 'stable');
    $acts = getTplPostButton(['name' => 'update', 'op' => 'check'], 'arrow-repeat', _UPDATE_CHECK).$pack['acts']
        .getTplPostButton(['name' => 'update', 'op' => 'audit'], 'shield-check', _UPDATE_AUDIT);
    $rows = array_merge($rows, $pack['rows'], [['label_html' => _FUNCTIONS, 'field_html' => $acts]]);
    return $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', ['rows' => $rows])]).$pack['html'];
}

# The package of the release found: the archive this site takes and the space it needs, a download cut midway, the unpacked package, the last refusal and integrity check
function getUpdatePack(array $state, string $chan): array {
    global $afile, $tpl;
    $out = ['rows' => [], 'acts' => '', 'html' => ''];
    $line = static fn(string $label, string $text): array => ['label_html' => $label, 'has_field_text' => true, 'field_text' => $text];
    $list = static fn(string $head, array $items): string => $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => sprintf($head, count($items)),
        'messages' => (count($items) > 100) ? array_merge(array_slice($items, 0, 100), [sprintf(_UPDATE_MORE, count($items) - 100)]) : $items]);
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
            $out['rows'][] = $line(_UPDATE_PACK, $name.' - '.sprintf(_UPDATE_STAGED, intval($stage['files'] ?? 0), date(_TIMESTRING, intval($stage['time'] ?? 0))));
            if (!empty($stage['nolist'])) $out['html'] .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _UPDATE_NOLIST]);
            if (!empty($stage['changed'])) $out['html'] .= $list(_UPDATE_CHANGED, array_map('strval', (array)$stage['changed']));
        } else {
            $temp = Update::getTempFile($data['version'], $kind);
            $have = is_file($temp) ? (int)filesize($temp) : 0;
            $room = Update::getSpaceNeed($pack, $have);
            $out['rows'][] = $line(_UPDATE_PACK, $name.(($have > 0) ? ' - '.sprintf(_UPDATE_GOT, filterSize($have), filterSize($pack['size'])) : ''));
            $free = ($room['free'] === null) ? sprintf(_UPDATE_NOFREE, filterSize($room['need'])) : sprintf(_UPDATE_NEED, filterSize($room['need']), filterSize($room['free']));
            $out['rows'][] = $line(_UPDATE_SPACE, $free.($room['ok'] ? '' : ' '.sprintf(_UPDATE_LOW, filterSize($room['need'] - intval($room['free'])))));
            if ($reader !== '' && $room['ok']) {
                $out['acts'] = getTplPostButton(['name' => 'update', 'op' => 'download'], 'cloud-download', ($have > 0) ? _UPDATE_GETMORE : _DOWNLOAD);
            }
        }
    }
    $fault = (array)$state['fault'];
    if (!$ready && ($fault['error'] ?? '') !== '') {
        $why = (string)$fault['error'].(((string)($fault['name'] ?? '') !== '') ? ' - '.(string)$fault['name'] : '');
        $out['rows'][] = $line(_UPDATE_FAULT, date(_TIMESTRING, intval($fault['time'] ?? 0)).' - '.sprintf(_UPDATE_REJECT, $why));
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
    $out['rows'][] = $line(_UPDATE_INTEG, $text);
    foreach (['changed' => _UPDATE_MODIFIED, 'missing' => _UPDATE_MISSING, 'extra' => _UPDATE_EXTRA] as $key => $head) {
        if (!empty($audit[$key])) $out['html'] .= $list($head, array_map('strval', (array)$audit[$key]));
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
    $radio = static function (string $name, string $label, string $value, array $opts, string $hint = ''): array {
        $lid = getFieldIds('', $name)['label'];
        $field = getTplRadioGroup(['labelledby' => $lid, 'name' => $name, 'value' => $value, 'options' => $opts]);
        return ['label_html' => $label, 'label_id' => $lid, 'hint_html' => $hint, 'field_html' => $field];
    };
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $sent = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _UPDATE_SENT, 'messages' => [_UPDATE_S_ID, _UPDATE_S_VER, _UPDATE_S_ENV, _UPDATE_S_ERR, _UPDATE_S_HOST,
        _UPDATE_S_FAIL]]);
    $rows = [
        $radio('channel', _UPDATE_CHANNEL, (($sets['channel'] ?? '') === 'beta') ? 'beta' : 'stable', [['value' => 'stable', 'label' => _UPDATE_STABLE],
            ['value' => 'beta', 'label' => _UPDATE_BETA]]),
        $radio('upcheck', _UPDATE_UPCHECK, (string)intval($sets['upcheck'] ?? 1), $yesno),
        $radio('upsite', _UPDATE_UPSITE, (string)intval($sets['upsite'] ?? 1), $yesno, _UPDATE_SITEHINT),
        $radio('keepbak', _UPDATE_KEEPBAK, (string)intval($sets['keepbak'] ?? 1), $yesno),
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
    case 'info': info(); break;
}
