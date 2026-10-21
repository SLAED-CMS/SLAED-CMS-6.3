<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('master')) die('Illegal file access');

require_once BASE_DIR.'/modules/master/common.php';

# The tabs of the master in their order; the journal link is added by getTplAdminTabs()
const MASTER_TABS = ['_MASTER_RELEASES', '_MASTER_SITES', '_STATISTIC', '_MASTER_NOTES', '_MASTER_KEYS', '_PREFERENCES'];

# Days without a check after which a site counts as silent on the tab of the sites
const MASTER_QUIET = 7;

# The header of the master with its tabs, the manual and the filter of a list; on a list the tabs of the three lists redraw in place
function getMasterHead(int $tab, string $find = '', bool $part = false): string {
    $ops = array_map(static fn(int $i): string => 'name=master&tab='.$i, array_keys(MASTER_TABS));
    $tabs = array_map('constant', MASTER_TABS);
    return getTplAdminTabs(['ops' => array_merge($ops, ['name=master&op=info']), 'tabs' => array_merge($tabs, [_MANUAL]), 'tab' => $tab,
        'part' => $part ? [0, 1, 3] : [], 'subtitle_html' => $find]);
}

# A moment of the database as the panel prints it, or the dash of a moment never recorded
function getMasterTime(?string $time): string {
    return ($time === null || $time === '') ? '-' : date(_TIMESTRING, (int)strtotime($time));
}

# The text of a refusal of the master by its reason
function getMasterReason(string $why): string {
    return match ($why) {
        'sign' => _MASTER_RSIGN,
        'expired' => _MASTER_REXP,
        'archive' => _MASTER_RZIP,
        'missing' => _MASTER_RMISS,
        'write' => _MASTER_RWRITE,
        default => _MASTER_RFORM,
    };
}

# One table of the panel from its head and its rows of cells; a cell is text unless it says html
function getMasterTable(array $head, array $rows): string {
    global $tpl;
    $html = '';
    foreach ($rows as $cells) {
        $html .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => array_map(static fn(array $one): array => isset($one['content_html'])
            ? $one : $one + ['has_content_text' => true], $cells)])]);
    }
    return $tpl->getHtmlFrag('table', ['is_fixed' => true, 'head' => $head, 'rows_html' => $html]);
}

function master(): void {
    global $tpl;
    $tab = getVar('get', 'tab', 'num', 0);
    if ($tab < 0 || $tab >= count(MASTER_TABS)) $tab = 0;
    $head = getMasterHead($tab, '', in_array($tab, [0, 3], true));
    $keys = getMasterKeys();
    $note = ($keys['error'] !== '') ? $tpl->getHtmlFrag('alert', ['type' => 'error', 'text' => sprintf(_MASTER_KEYERR, htmlspecialchars($keys['error'], ENT_QUOTES))]) : '';
    setHead();
    echo match ($tab) {
        0 => getMasterReleases($head, $note),
        1 => getMasterSites($note),
        3 => getMasterNoteList($head, $note),
        2 => $head.$note.getMasterStats(),
        4 => $head.$note.getMasterKeyList($keys),
        default => $head.$note.getMasterSettings(),
    };
    setFoot();
}

# The tab of releases: what waits in inbox/ above, then every accepted release with its state, share and the deadline of its served manifest
function getMasterReleases(string $head, string $note): string {
    global $afile, $db, $tpl;
    $rows = [];
    foreach (getMasterInbox()['releases'] as $ver) {
        $act = getTplPostAction(['name' => 'master', 'op' => 'accept', 'ver' => $ver], 'box-arrow-in-down', _MASTER_ACCEPT, _MASTER_ACCEPT.' '.$ver.'?');
        $rows[] = [['content_text' => $ver], ['content_text' => 'inbox/'.$ver.'/'],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => [$act]])]];
    }
    if ($rows) $note .= getMasterTable([['content' => _VERSION], ['content' => _MASTER_INBOX, 'is_truncate' => true], ['content' => _FUNCTIONS, 'is_col_actions' => true,
        'nosort' => true]], $rows);
    $res = $db->getSqlQuery('SELECT ver, state, pauseby, pfail, pok, share, accepted, changed FROM '.PREFIX_DB.'_master_release');
    $list = $res ? ($db->getSqlRows($res) ?: []) : [];
    usort($list, static fn(array $one, array $two): int => version_compare($two['ver'], $one['ver']));
    $rows = [];
    $warn = [];
    foreach ($list as $row) {
        $ver = $row['ver'];
        $chan = in_array($row['state'], ['beta', 'stable'], true) ? $row['state'] : 'stable';
        $ends = intval(getMasterManifest($ver, $chan)['data']['expires'] ?? 0);
        $served = in_array($row['state'], ['beta', 'stable'], true);
        if ($served && $ends - time() < 864000) $warn[] = sprintf(_MASTER_EXPWARN, $ver, date(_TIMESTRING, $ends));
        $state = match ($row['state']) {
            'pause' => _PAUSE.' - '.(($row['pauseby'] === 'auto') ? sprintf(_MASTER_BYAUTO, $row['pfail'], $row['pok']) : _MASTER_BYOWNER),
            'wait' => _MASTER_WAIT,
            default => $row['state'],
        };
        $keys = ['name' => 'master', 'op' => 'state', 'ver' => $ver];
        $acts = [];
        if ($row['state'] !== 'beta') $acts[] = getTplPostAction($keys + ['state' => 'beta'], 'cone-striped', _MASTER_TOBETA);
        if ($row['state'] !== 'stable') $acts[] = getTplPostAction($keys + ['state' => 'stable'], 'check2-circle', _MASTER_TOSTABLE);
        if ($served) $acts[] = getTplPostAction($keys + ['state' => 'pause'], 'pause-circle', _PAUSE, _PAUSE.' '.$ver.'?');
        $acts[] = ['href' => $afile.'.php?name=master&op=share&ver='.$ver, 'icon_name' => 'percent', 'title' => _MASTER_SHARE];
        $rows[] = ['name' => $ver, 'cells' => [
            ['has_content_text' => true, 'content_text' => $ver],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => $state],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $row['share'].' %'],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getMasterTime($row['accepted'])],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getMasterTime($row['changed'])],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => $ends ? date(_TIMESTRING, $ends) : '-'],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $acts])],
        ]];
    }
    if ($warn) $note = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => $warn]).$note;
    return getTplAdminList([
        'head_html' => $head,
        'note_html' => $note,
        'is_fixed' => true,
        'head' => [['content' => _VERSION], ['content' => _STATUS, 'is_truncate' => true], ['content' => _MASTER_SHARE, 'is_col_count' => true],
            ['content' => _MASTER_ACCEPTED, 'is_col_date' => true], ['content' => _MASTER_CHANGED, 'is_col_date' => true], ['content' => _MASTER_EXPIRES, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]],
        'rows' => $rows,
        'count' => count($rows),
    ]);
}

# The tab of sites: a search by domain, the silent ones on request, a page of rows with the sid and the hint for a personal note
function getMasterSites(string $note): string {
    global $afile, $db, $tpl;
    $find = mb_substr(trim(getVar('get', 'q', 'text', '')), 0, 100);
    $quiet = getVar('get', 'quiet', 'num', 0) === 1;
    $where = [];
    $args = [];
    if ($find !== '') {
        $where[] = 'host LIKE :find';
        $args['find'] = '%'.addcslashes(mb_strtolower($find), '%_\\').'%';
    }
    if ($quiet) $where[] = 'seen < NOW() - INTERVAL '.MASTER_QUIET.' DAY';
    $cond = $where ? ' WHERE '.implode(' AND ', $where) : '';
    $res = $db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_master_site'.$cond, $args);
    $all = intval(($res ? $db->getSqlRow($res) : [0])[0] ?? 0);
    $pages = max(1, (int)ceil($all / 50));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $res = $db->getSqlQuery('SELECT sid, host, ver, chan, kseq, php, db, dbver, ext, ep, es, failver, failstep, failed, added, seen FROM '.PREFIX_DB.'_master_site'.$cond
        .' ORDER BY seen DESC LIMIT 50 OFFSET '.(($num - 1) * 50), $args);
    $rows = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        $tips = [['label' => _MASTER_SID, 'has_value_text' => true, 'value_text' => $row['sid']], ['label' => 'kseq', 'has_value_text' => true, 'value_text' => $row['kseq']]];
        if ($row['failver'] !== '') {
            $tips[] = ['label' => _MASTER_FAILS, 'has_value_text' => true, 'value_text' => $row['failver'].', '.sprintf(_MASTER_STEP, $row['failstep']).', '
                .getMasterTime($row['failed'])];
        }
        $tips[] = ['label' => _MASTER_NOTES, 'has_value_text' => true, 'value_text' => 'php tools/release.php note --level info --sid '.$row['sid']];
        $ext = implode(', ', array_keys(array_filter(['sodium' => $row['ext'] & 1, 'zip' => $row['ext'] & 2, 'phar' => $row['ext'] & 4]))) ?: '-';
        $host = ($row['host'] !== '') ? $row['host'] : _MASTER_NOHOST;
        $rows[] = ['name' => $host, 'cells' => [
            ['is_truncate' => true, 'title_text' => $row['host'], 'has_content_text' => true, 'content_text' => $host,
                'prefix_html' => $tpl->getHtmlFrag('popover', ['items' => $tips])],
            ['has_content_text' => true, 'content_text' => $row['ver']], ['has_content_text' => true, 'content_text' => $row['chan']],
            ['has_content_text' => true, 'content_text' => $row['php']], ['has_content_text' => true, 'content_text' => trim($row['db'].' '.$row['dbver'])],
            ['has_content_text' => true, 'content_text' => $ext], ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $row['ep'].' / '.$row['es']],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getMasterTime($row['added'])],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getMasterTime($row['seen'])],
        ]];
    }
    $form = $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'master'], ['nameattr' => 'tab', 'valueattr' => '1']],
        'fields' => [
            ['field_id' => 'sl-master-q', 'label' => _MASTER_DOMAIN, 'name_attr' => 'q', 'itype' => 'search', 'value' => $find],
            ['field_id' => 'sl-master-quiet', 'label' => _STATUS, 'name_attr' => 'quiet', 'options' => [['value_attr' => '0', 'label_text' => _ALL, 'is_selected' => !$quiet],
                ['value_attr' => '1', 'label_text' => sprintf(_MASTER_SILENT, MASTER_QUIET), 'is_selected' => $quiet]]],
        ],
    ]);
    return getTplAdminList([
        'head_html' => getMasterHead(1, $form, true),
        'key' => 'master-sites',
        'note_html' => $note,
        'is_fixed' => true,
        'head' => [['content' => _MASTER_DOMAIN, 'is_truncate' => true], ['content' => _VERSION], ['content' => _MASTER_CHAN], ['content' => 'PHP'],
            ['content' => _DATABASE], ['content' => _MASTER_EXT], ['content' => _MASTER_ERRS, 'is_col_count' => true], ['content' => _MASTER_FIRST, 'is_col_date' => true],
            ['content' => _MASTER_LAST, 'is_col_date' => true]],
        'rows' => $rows,
        'href' => $afile.'.php?name=master&tab=1&'.http_build_query(['q' => $find, 'quiet' => $quiet ? 1 : 0]).'&',
        'page' => $num,
        'pages' => $pages,
        'count' => $all,
        'limit' => 50,
    ]);
}

# The tab of statistics: the totals of one day by channel, version, PHP and database, and the failed installations by version and step from the sites
function getMasterStats(): string {
    global $afile, $db, $tpl;
    $res = $db->getSqlQuery('SELECT DISTINCT day FROM '.PREFIX_DB.'_master_stat ORDER BY day DESC LIMIT 90');
    $days = array_column($res ? ($db->getSqlRows($res) ?: []) : [], 'day');
    $day = getVar('get', 'day', 'text', '');
    if (!in_array($day, $days, true)) $day = (string)($days[0] ?? '');
    $opts = '';
    foreach ($days as $one) $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $one, 'label_text' => $one, 'is_selected' => $one === $day]);
    $cont = $days ? $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'master'], ['nameattr' => 'tab', 'valueattr' => '2']],
        'content_html' => _DATE.': '.$tpl->getHtmlFrag('select', ['name_attr' => 'day', 'options_html' => $opts, 'select_attr' => 'OnChange="submit()"']),
    ])]) : '';
    $res = $db->getSqlQuery('SELECT chan, ver, php, db, sites, nosod, nozip, errs, fails FROM '.PREFIX_DB.'_master_stat WHERE day = :day ORDER BY sites DESC', ['day' => $day]);
    $rows = [];
    foreach (($res && $day !== '') ? ($db->getSqlRows($res) ?: []) : [] as $row) {
        $rows[] = [['content_text' => $row['chan']], ['content_text' => $row['ver']], ['content_text' => $row['php']], ['content_text' => $row['db']],
            ['is_col_count' => true, 'content_text' => $row['sites']], ['is_col_count' => true, 'content_text' => $row['nosod']],
            ['is_col_count' => true, 'content_text' => $row['nozip']], ['is_col_count' => true, 'content_text' => $row['errs']],
            ['is_col_count' => true, 'content_text' => $row['fails']]];
    }
    $cont .= $rows ? getMasterTable([['content' => _MASTER_CHAN], ['content' => _VERSION], ['content' => 'PHP'], ['content' => _DATABASE],
        ['content' => _MASTER_COUNT, 'is_col_count' => true], ['content' => _MASTER_NOSOD, 'is_col_count' => true], ['content' => _MASTER_NOZIP, 'is_col_count' => true],
        ['content' => _MASTER_ERRS, 'is_col_count' => true], ['content' => _MASTER_FAILS, 'is_col_count' => true]], $rows)
        : $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['text' => _NO_INFO])]);
    $res = $db->getSqlQuery('SELECT failver, failstep, COUNT(*) AS num FROM '.PREFIX_DB.'_master_site WHERE failver <> \'\' GROUP BY failver, failstep'
        .' ORDER BY failver DESC, num DESC');
    $rows = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        $rows[] = [['content_text' => $row['failver']], ['content_text' => sprintf(_MASTER_STEP, $row['failstep'])], ['is_col_count' => true, 'content_text' => $row['num']]];
    }
    if ($rows) $cont .= getMasterTable([['content' => _MASTER_FAILS], ['content' => _MASTER_STEPS], ['content' => _MASTER_COUNT, 'is_col_count' => true]], $rows);
    return $cont;
}

# The tab of notes: what waits in inbox/notes/ above, then the accepted notes newest first with their addressee, range, level and deadline, fifty to a page
function getMasterNoteList(string $head, string $note): string {
    global $afile, $db, $tpl;
    $rows = [];
    foreach (getMasterInbox()['notes'] as $id) {
        $act = getTplPostAction(['name' => 'master', 'op' => 'noteadd', 'id' => $id], 'box-arrow-in-down', _MASTER_ACCEPT);
        $rows[] = [['is_col_id' => true, 'content_text' => $id], ['content_text' => 'inbox/notes/'.$id.'.json'],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => [$act]])]];
    }
    if ($rows) $note .= getMasterTable([['content' => _ID, 'is_col_id' => true], ['content' => _MASTER_INBOX, 'is_truncate' => true],
        ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]], $rows);
    $res = $db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_master_note');
    $all = intval(($res ? $db->getSqlRow($res) : [0])[0] ?? 0);
    $pages = max(1, (int)ceil($all / 50));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $res = $db->getSqlQuery('SELECT id, sid, ver, level, until, active, body FROM '.PREFIX_DB.'_master_note ORDER BY id DESC LIMIT 50 OFFSET '.(($num - 1) * 50));
    $rows = [];
    foreach (($res ? ($db->getSqlRows($res) ?: []) : []) as $row) {
        $data = json_decode($row['body'], true);
        $acts = $row['active'] ? [getTplPostAction(['name' => 'master', 'op' => 'noteoff', 'id' => $row['id'], 'num' => $num], 'x-circle', _DEACTIVATE, _DEACTIVATE.'?')] : [];
        $rows[] = ['name' => '#'.$row['id'], 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $row['id']],
            ['has_content_text' => true, 'content_text' => $row['level']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => ($row['sid'] !== '') ? $row['sid'] : _MASTER_TOALL],
            ['has_content_text' => true, 'content_text' => ($row['ver'] !== '') ? $row['ver'] : _ALL],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => ($row['until'] !== null) ? getMasterTime($row['until']) : _MASTER_NOEND],
            ['is_truncate' => true, 'title_text' => (string)($data['ru'] ?? ''), 'has_content_text' => true, 'content_text' => (string)($data['ru'] ?? '')],
            ['is_col_status' => true, 'has_content_text' => true, 'content_text' => $row['active'] ? _YES : _NO],
            ['is_col_actions' => true, 'content_html' => $acts ? $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $acts]) : ''],
        ]];
    }
    return getTplAdminList([
        'head_html' => $head,
        'note_html' => $note,
        'is_fixed' => true,
        'head' => [['content' => _ID, 'is_col_id' => true], ['content' => _MASTER_LEVEL], ['content' => _MASTER_TO, 'is_truncate' => true],
            ['content' => _MASTER_RANGE], ['content' => _MASTER_UNTIL, 'is_col_date' => true], ['content' => _TEXT, 'is_truncate' => true],
            ['content' => _STATUS, 'is_col_status' => true], ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]],
        'rows' => $rows,
        'href' => $afile.'.php?name=master&tab=3&',
        'key' => 'master-notes',
        'page' => $num,
        'pages' => $pages,
        'count' => $all,
        'limit' => 50,
    ]);
}

# The tab of keys, read only: the fingerprints of the factory keys and of the keys in force, and every key statement in force with its kseq
function getMasterKeyList(array $keys): string {
    $print = static fn(string $key): string => trim(chunk_split(Update::getKeyPrint(Update::getRawBytes($key, 32)), 4, ' '));
    $fact = Update::getFactoryKeys();
    $rows = [[['content_text' => _MASTER_PRIMARY.' - '._MASTER_FACTORY], ['is_truncate' => true, 'content_text' => $print($fact['primary'])]],
        [['content_text' => _MASTER_RECOVERY.' - '._MASTER_FACTORY], ['is_truncate' => true, 'content_text' => $print($fact['recovery'])]],
        [['content_text' => _MASTER_PRIMARY.' - '._MASTER_INFORCE], ['is_truncate' => true, 'content_text' => $print($keys['keys']['primary'])]],
        [['content_text' => _MASTER_RECOVERY.' - '._MASTER_INFORCE], ['is_truncate' => true, 'content_text' => $print($keys['keys']['recovery'])]],
        [['content_text' => 'kseq'], ['content_text' => $keys['keys']['kseq']]]];
    foreach ($keys['list'] as $one) {
        $rows[] = [['content_text' => 'inbox/keys/'.$one['kseq'].'.json'], ['is_truncate' => true, 'content_text' => $one['time'] ? date(_TIMESTRING, $one['time']) : '-']];
    }
    return getMasterTable([['content' => _MASTER_KEYS], ['content' => _MASTER_PRINT, 'is_truncate' => true]], $rows);
}

# The tab of settings: the terms of silent sites and daily totals, and the threshold of the automatic pause
function getMasterSettings(): string {
    global $afile, $conf, $tpl;
    $field = static fn(string $name, string $label, int $val, int $max): array => ['label_for' => 'f-'.$name, 'label_html' => $label,
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $name, 'input_id' => 'f-'.$name, 'value_attr' => (string)$val, 'is_required' => true,
        'input_attr' => 'min="1" max="'.$max.'"'])];
    $rows = [$field('silent', _MASTER_KEEPSITE, intval($conf['master']['silent'] ?? 180), 3650), $field('keep', _MASTER_KEEPSTAT, intval($conf['master']['keep'] ?? 730), 3650),
        $field('limit', _MASTER_LIMIT, intval($conf['master']['limit'] ?? 5), 100)];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=master&op=save',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('master')]],
        'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MASTER_JOBS]).$tpl->getHtmlPart('div', ['rows' => $rows]),
        'submit_label' => _SAVECHANGES,
    ]);
    return checkPerms(CONFIG_DIR.'/master.php').$tpl->getHtmlPart('box', ['content_html' => $form]);
}

# Put the jobs modules/master/scheduler.php declares into the scheduler of this site once: the shipped scheduler never carries them, since only slaed.net runs the master
function setMasterJobs(): bool {
    global $conf;
    $data = $conf['scheduler'] ?? [];
    $add = false;
    foreach (getSchedulerModuleJobs() as $name => $job) {
        if ($job['mod'] !== 'master' || isset($data['jobs'][$name])) continue;
        unset($job['mod'], $job['run']);
        $data['jobs'][$name] = getSchedulerJob($name, $job + ['active' => '1', 'manual' => '1', 'settings' => []]);
        $add = true;
    }
    if (!$add) return true;
    ksort($data['jobs']);
    return setConfigFile('scheduler.php', $data);
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('master');
    if (!$warn) {
        $data = ['silent' => max(1, min(3650, getVar('post', 'silent', 'num', 180))), 'keep' => max(1, min(3650, getVar('post', 'keep', 'num', 730))),
            'limit' => max(1, min(100, getVar('post', 'limit', 'num', 5)))];
        $warn = !setConfigFile('master.php', $data) || !setMasterJobs();
        if ($warn) {
            setRedirect($afile.'.php?name=master&tab=5', false, 302, _ERROR, true);
            return;
        }
    }
    setRedirect($afile.'.php?name=master&tab=5', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function accept(): void {
    global $afile;
    $ver = getVar('post', 'ver', 'text', '');
    if (!checkAdminPost('master')) {
        setRedirect($afile.'.php?name=master', false, 302, _TOKENMISS, true);
        return;
    }
    setAdminLog(['ver' => $ver]);
    $why = addMasterRelease($ver);
    setRedirect($afile.'.php?name=master', false, 302, ($why === '') ? sprintf(_MASTER_DONE, $ver) : sprintf(_MASTER_REFUSED, getMasterReason($why)), $why !== '');
}

function state(): void {
    global $afile;
    $ver = getVar('post', 'ver', 'text', '');
    $state = getVar('post', 'state', 'text', '');
    if (!checkAdminPost('master')) {
        setRedirect($afile.'.php?name=master', false, 302, _TOKENMISS, true);
        return;
    }
    setAdminLog(['ver' => $ver, 'state' => $state]);
    $done = setMasterState($ver, $state);
    setRedirect($afile.'.php?name=master', false, 302, $done ? _SUCCSAVE : _ERROR, !$done);
}

function share(): void {
    global $afile, $db, $tpl;
    $ver = getVar('get', 'ver', 'text', '');
    $res = $db->getSqlQuery('SELECT share FROM '.PREFIX_DB.'_master_release WHERE ver = :ver', ['ver' => $ver]);
    $row = $res ? $db->getSqlRow($res) : false;
    if (!$row) {
        setRedirect($afile.'.php?name=master', false, 302, _NO_INFO, true);
        return;
    }
    setHead();
    $field = $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'share', 'input_id' => 'f-share', 'value_attr' => (string)$row['share'], 'is_required' => true,
        'input_attr' => 'min="0" max="100"']);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=master&op=setshare',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken('master')], ['nameattr' => 'ver', 'valueattr' => $ver]],
        'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_MASTER_SHAREDESC, htmlspecialchars($ver, ENT_QUOTES))])
            .$tpl->getHtmlPart('div', ['rows' => [['label_for' => 'f-share', 'label_html' => _MASTER_SHARE, 'field_html' => $field]]]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo getMasterHead(0).$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function setshare(): void {
    global $afile, $db;
    $ver = getVar('post', 'ver', 'text', '');
    $share = max(0, min(100, getVar('post', 'share', 'num', 100)));
    if (!checkAdminPost('master')) {
        setRedirect($afile.'.php?name=master', false, 302, _TOKENMISS, true);
        return;
    }
    setAdminLog(['ver' => $ver, 'share' => $share]);
    $res = $db->getSqlQuery('UPDATE '.PREFIX_DB.'_master_release SET share = :share, changed = NOW() WHERE ver = :ver', ['share' => $share, 'ver' => $ver]);
    setRedirect($afile.'.php?name=master', false, 302, $res ? _SUCCSAVE : _ERROR, !$res);
}

function noteadd(): void {
    global $afile;
    $id = getVar('post', 'id', 'num', 0);
    if (!checkAdminPost('master')) {
        setRedirect($afile.'.php?name=master&tab=3', false, 302, _TOKENMISS, true);
        return;
    }
    $why = addMasterNote($id);
    setRedirect($afile.'.php?name=master&tab=3', false, 302, ($why === '') ? sprintf(_MASTER_DONE, $id) : sprintf(_MASTER_REFUSED, getMasterReason($why)), $why !== '');
}

function noteoff(): void {
    global $afile, $db;
    $id = getVar('post', 'id', 'num', 0);
    if (!checkAdminPost('master')) {
        setRedirect($afile.'.php?name=master&tab=3', false, 302, _TOKENMISS, true);
        return;
    }
    $res = $db->getSqlQuery('UPDATE '.PREFIX_DB.'_master_note SET active = 0 WHERE id = :id', ['id' => $id]);
    $done = $res && $res->rowCount() > 0;
    setRedirect($afile.'.php?name=master&tab=3&num='.getVar('post', 'num', 'num', 1), false, 302, $done ? _SUCCSAVE : _ERROR, !$done);
}

function info(): void {
    $ops = array_map(static fn(int $i): string => 'name=master&tab='.$i, array_keys(MASTER_TABS));
    setTplAdminInfoPage(['ops' => array_merge($ops, ['name=master&op=info']), 'tabs' => array_merge(array_map('constant', MASTER_TABS), [_MANUAL])]);
}

switch ($op) {
    default: master(); break;
    case 'save': save(); break;
    case 'accept': accept(); break;
    case 'state': state(); break;
    case 'share': share(); break;
    case 'setshare': setshare(); break;
    case 'noteadd': noteadd(); break;
    case 'noteoff': noteoff(); break;
    case 'info': info(); break;
}
