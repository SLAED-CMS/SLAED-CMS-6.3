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
    $rows[] = ['label_html' => _FUNCTIONS, 'field_html' => getTplPostButton(['name' => 'update', 'op' => 'check'], 'arrow-repeat', _UPDATE_CHECK)];
    return $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', ['rows' => $rows])]);
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
    case 'hide': hide(); break;
    case 'info': info(); break;
}
