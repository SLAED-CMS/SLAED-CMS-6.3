<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !defined('UPDATE_FILE')) die('Illegal file access');

# The light mode of the panel, which public/admin.php enters while the code is newer than the last database step marked done
require_once BASE_DIR.'/core/admin.php';
require_once BASE_DIR.'/core/classes/template.php';
require_once BASE_DIR.'/core/classes/captcha.php';
require_once BASE_DIR.'/core/classes/update.php';

# The stops of the light mode: the login of a super-administrator, the database steps and the open site
const UPDATE_STOPS = ['_LOGIN', '_UPDATE_STEPS', '_UPDATE_DONE'];

# The hidden fields of a form of the light mode: its operation and the token of its scope
function getUpdateHidden(string $op): array {
    return [['name_attr' => 'op', 'value_attr' => $op], ['name_attr' => 'token', 'value_attr' => getSiteToken('update')]];
}

# The stop of the login: the light mode admits only a super-administrator, whom it checks against the table of administrators alone
function setUpdateLogin(string $fail): void {
    global $afile, $tpl;
    $rows = [];
    foreach ([['name', _NICKNAME, ['itype' => 'text', 'maxlength_num' => 25, 'autocomplete_attr' => 'username']],
        ['pwd', _PASSWORD, ['itype' => 'password', 'autocomplete_attr' => 'current-password']]] as [$key, $label, $opts]) {
        $field = $tpl->getHtmlFrag('input', ['name_attr' => $key, 'input_id' => 'u-'.$key, 'is_required' => true] + $opts);
        $rows[] = ['label' => $label, 'input_id' => 'u-'.$key, 'field_html' => $field];
    }
    $alert = ($fail !== '') ? $tpl->getHtmlFrag('alert', ['type' => 'error', 'text' => $fail]) : '';
    setCardPage(_UPDATE_STEPS, array_map('constant', UPDATE_STOPS), 0, ['action' => $afile.'.php', 'hidden' => getUpdateHidden('login'), 'road_text' => _UPDATE_STEPS,
        'lead' => _UPDATE_LOGIN, 'rows' => $rows, 'alert_html' => $alert, 'next_text' => _LOGIN]);
}

# Admit a super-administrator by name and password with the failure streak of the panel login; the session gets the same entry the panel login writes
function checkUpdateLogin(): void {
    global $db, $afile, $conf;
    $name = htmlspecialchars(trim(substr(getVar('post', 'name', 'raw', ''), 0, 25)));
    $pwd = getVar('post', 'pwd', 'raw', '');
    $good = checkSiteToken(getVar('post', 'token', 'text'), 'update') && CaptchaStore::counter('lf:admin:'.getIp()) < 10;
    $sql = 'SELECT id, name, password, editor, super FROM '.PREFIX_DB.'_admins WHERE name = :name';
    $res = ($good && $name !== '' && $pwd !== '') ? $db->getSqlQuery($sql, ['name' => $name]) : false;
    $row = $res ? ($db->getSqlRow($res) ?: []) : [];
    if (($row[1] ?? '') !== $name || intval($row[4] ?? 0) !== 1 || !checkPassHash($pwd, (string)($row[2] ?? ''))) {
        Captcha::registerLoginFailure('admin');
        addUpdateLogin((isset($row[1]) && $row[1] === $name) ? intval($row[0]) : 0, $good ? 'password' : 'blocked', $name);
        setUpdateLogin(_LOGININCOR);
        return;
    }
    $_SESSION[$conf['admin_c']] = base64_encode($row[0].':'.$row[1].':'.$row[2].':'.$row[3]);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET ip = :ip, lastvis = now() WHERE id = :id', ['ip' => getIp(), 'id' => $row[0]]);
    Captcha::clearLoginFailures('admin');
    addUpdateLogin(intval($row[0]), 'ok', $name);
    setRedirect($afile.'.php');
}

# Note a login of the light mode in admin.log as the panel login notes it; the table of logins is left alone, since a step may change it
function addUpdateLogin(int $uid, string $res, string $name): void {
    global $conf;
    if (empty($conf['security']['log_a'])) return;
    $ctx = ['event' => 'login', 'result' => $res, 'way' => 'login', 'uid' => $uid, 'login' => substr($name, 0, 25), 'admin' => '', 'user' => ''];
    Logger::addAdmin(($res === 'ok') ? 'info' : 'warning', ($res === 'ok') ? 'Login accepted' : 'Login refused', $ctx);
}

# The stop of the database steps: the version of the code against the last mark, the backup first, then each waiting version with what it runs
function setUpdateSteps(string $fail): void {
    global $afile, $tpl;
    $mark = (string)getStepMark(CONFIG_DIR);
    $checks = [['turn' => 0, 'text' => _DATABASE, 'note' => _UPDATE_BACKUP]];
    foreach (Update::getStepList($mark) as $ver => $has) {
        $note = implode(' + ', array_keys(array_filter(['SQL' => $has['sql'], 'PHP' => $has['php']])));
        $checks[] = ['turn' => count($checks), 'text' => $ver, 'note' => ($note !== '') ? $note : _UPDATE_MARK];
    }
    $alert = ($fail !== '') ? $tpl->getHtmlFrag('alert', ['type' => 'error', 'text' => htmlspecialchars($fail, ENT_QUOTES, 'UTF-8')]) : '';
    $lead = sprintf(_UPDATE_LEAD, SL_VERSION, ($mark !== '') ? $mark : _NO);
    setCardPage(_UPDATE_STEPS, array_map('constant', UPDATE_STOPS), 1, ['action' => $afile.'.php', 'hidden' => getUpdateHidden('steps'), 'road_text' => _UPDATE_STEPS,
        'lead' => $lead, 'checks' => $checks, 'alert_html' => $alert, 'next_text' => _UPDATE_APPLY, 'is_install' => true]);
}

# Back up the database, then run the waiting steps under one lock, so a second click waits and finds nothing left; the mark of the code opens the site
function addUpdateSteps(): void {
    global $db, $afile, $conf;
    if (!checkAdminPost('update')) {
        setUpdateSteps(_TOKENMISS);
        return;
    }
    $lock = fopen(CACHE_DIR.'/update-steps.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        setUpdateSteps(_ERROR);
        return;
    }
    if (Update::getStepList((string)getStepMark(CONFIG_DIR)) === []) setRedirect($afile.'.php', false, 302, _UPDATE_APPLIED);
    require_once BASE_DIR.'/core/classes/backup.php';
    $sett = $conf['scheduler']['jobs']['dbbackup']['settings'] ?? [];
    $back = (new Backup($db, ['name' => (string)($conf['db']['name'] ?? '')], is_array($sett) ? $sett : [], BACKUP_DIR))->addDatabaseBackup();
    $res = (($back['status'] ?? '') === 'success') ? Update::addSteps($db, $conf) : ['ok' => false, 'ver' => '', 'error' => (string)($back['message'] ?? '')];
    setAdminLog(['result' => $res['ok'] ? 'done' : 'failed', 'ver' => $res['ver']]);
    if ($res['ok']) setRedirect($afile.'.php', false, 302, _UPDATE_APPLIED);
    setUpdateSteps(($res['ver'] === '') ? sprintf(_UPDATE_NOBACK, $res['error']) : sprintf(_UPDATE_STEPFAIL, $res['ver'], $res['error']));
}

getLang('admin');
$tpl = new Template('admin');
$uop = getVar('post', 'op', 'var', '');
if ($uop === 'login') checkUpdateLogin();
elseif (!isAdmin(true)) setUpdateLogin('');
elseif ($uop === 'steps') addUpdateSteps();
else setUpdateSteps('');
exit;
