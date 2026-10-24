<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function ratings(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'], 'tabs' => [_HOME, _RATINGS_VOTES, _MANUAL]]);
    $cont .= checkPerms(CONFIG_DIR.'/ratings.php');
    if (($conf['update']['ratings'] ?? '') !== '6.3.0') {
        echo $cont.$tpl->getHtmlFrag('alert', ['text' => _RATINGS_NOMARK, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        setFoot();
        return;
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $flags = ['in' => ['active', _C_21], 'view' => ['detail', _C_22], 'guest' => ['guests', _RATINGS_GUESTS]];
    $blocks = '';
    $types = getNodeTypeMap();
    $scopes = ['account' => getModuleName('account'), 'forum' => getModuleName('forum')];
    foreach ($types as $name => $type) {
        $label = (str_starts_with($type->title, '_') && defined($type->title)) ? constant($type->title) : $type->title;
        $scopes['node.'.$name] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    }
    foreach (array_keys($scopes) as $i => $val) {
        $rule = str_starts_with($val, 'node.') ? $types[substr($val, 5)]->rating : ($conf['ratings'][$val] ?? []);
        $rows = [[
            'label_html' => _VOTING_TIME,
            'is_ratings_inner' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'time['.$i.']',
                'value_attr' => (string)intdiv(intval($rule['period'] ?? 0), 86400),
                'is_config' => true,
                'is_ratings_days' => true,
            ]),
        ]];
        foreach ($flags as $key => [$name, $label]) {
            $rows[] = [
                'label_html' => $label,
                'is_ratings_inner' => true,
                'label_id' => $labid = getFieldIds('', $i.$key)['label'],
                'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => $i.$key, 'value' => (string)($rule[$name] ?? '0'), 'options' => $yesno]),
            ];
        }
        $blocks .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'ratings'.$i,
            'label_html' => $scopes[$val].' - '.$val,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows]),
        ]);
    }
    $hidden = [
        ['nameattr' => 'name', 'valueattr' => 'ratings'],
        ['nameattr' => 'op', 'valueattr' => 'save'],
        ['nameattr' => 'token', 'valueattr' => getSiteToken('ratings')],
    ];
    $hidden[] = ['nameattr' => 'scopes', 'valueattr' => implode(',', array_keys($scopes))];
    foreach ($types as $name => $type) $hidden[] = ['nameattr' => 'ver['.$name.']', 'valueattr' => (string)$type->version];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => $hidden,
        'content_html' => $blocks,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $warn = !checkAdminPost('ratings') || ($conf['update']['ratings'] ?? '') !== '6.3.0';
    $text = _TOKENMISS;
    $content = [];
    $types = getNodeTypeMap();
    $scopes = array_merge(['account', 'forum'], array_map(fn(string $v): string => 'node.'.$v, array_keys($types)));
    if (!$warn) {
        foreach ($scopes as $i => $val) {
            $days = trim(getVar('post', 'time['.$i.']', 'raw', ''));
            if (!preg_match('/^(?:0|[1-9][0-9]{0,15})$/D', $days) || intval($days) > intdiv(PHP_INT_MAX, 86400)) $warn = true;
            $content[$val] = ['active' => getVar('post', $i.'in', 'num', 0) ? '1' : '0', 'period' => (string)(intval($days) * 86400)];
            $content[$val] += ['detail' => getVar('post', $i.'view', 'num', 0) ? '1' : '0', 'guests' => getVar('post', $i.'guest', 'num', 0) ? '1' : '0'];
        }
        $text = _RATINGS_BADDAYS;
    }
    $sent = getVar('post', 'scopes', 'raw', '');
    if (!$warn && $sent !== implode(',', $scopes)) {
        $warn = true;
        $seen = explode(',', $sent);
        $text = sprintf(_NODE_STALE, htmlspecialchars(implode(', ', array_merge(array_diff($scopes, $seen), array_diff($seen, $scopes))), ENT_QUOTES, 'UTF-8'));
    }
    if (!$warn) {
        $own = array_filter($content, fn(string $v): bool => !str_starts_with($v, 'node.'), ARRAY_FILTER_USE_KEY);
        $warn = !setConfigFile(static function (array $base, Closure $save) use ($own): string {
            $base['ratings'] = array_replace($base['ratings'], $own);
            ksort($base['ratings']);
            return $save($base) ? 'committed' : 'aborted';
        });
        $text = $warn ? (getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP) : _SUCCSAVE;
        $vers = getVar('post', 'ver[]', '', []);
        foreach ($types as $name => $type) {
            if ($warn || $content['node.'.$name] === $type->rating) continue;
            $fail = updateNodeTypePart($name, 'rating', $content['node.'.$name], intval($vers[$name] ?? 0));
            $warn = $fail !== '';
            if ($warn) $text = $fail;
        }
    }
    setRedirect($afile.'.php?name=ratings', false, 302, $text, $warn);
}

function votes(): void {
    global $afile, $tpl;
    $names = ['account' => getModuleName('account'), 'forum' => getModuleName('forum')];
    foreach (array_keys(getNodeTypeMap()) as $name) $names['node.'.$name] = getModuleName($name);
    $scope = getVar('get', 'scope', 'raw', '');
    $scope = (is_string($scope) && isset($names[$scope])) ? $scope : '';
    $mid = $scope !== '' ? getVar('get', 'mid', 'num', 0) : 0;
    $vote = getVar('get', 'vote', 'num', 0);
    $keep = array_filter(['scope' => $scope, 'mid' => $mid]);
    $lim = getListLimit(50, fn(): int => getRatingService()->getRatingList($scope, $mid, 1, 1)['total']);
    $list = getRatingService()->getRatingList($scope, $mid, max(1, getVar('get', 'num', 'num', 1)), $lim);
    $link = $afile.'.php?name=ratings&op=votes'.($keep ? '&'.http_build_query($keep) : '');
    setHead();
    $note = $list['ok'] ? '' : $tpl->getHtmlFrag('alert', ['text' => _RATINGS_FAIL, 'is_warn' => true]);
    if ($vote) {
        $field = $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'reason', 'input_id' => 'f-reason', 'value_attr' => '', 'maxlength_num' => '255',
            'is_config' => true, 'is_required' => true]);
        $note .= $tpl->getHtmlPart('box', ['title' => _RATINGS_ANNUL.' #'.$vote, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => array_merge([
                ['nameattr' => 'name', 'valueattr' => 'ratings'],
                ['nameattr' => 'op', 'valueattr' => 'annul'],
                ['nameattr' => 'vote', 'valueattr' => (string)$vote],
                ['nameattr' => 'num', 'valueattr' => (string)$list['page']],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('ratings')],
            ], array_map(static fn($k, $v): array => ['nameattr' => $k, 'valueattr' => (string)$v], array_keys($keep), $keep)),
            'rows' => [['label_for' => 'f-reason', 'label_html' => _RATINGS_REASON, 'field_html' => $field]],
            'submit_label' => _RATINGS_ANNUL,
        ])]);
    }
    $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $scope === '']];
    foreach ($names as $name => $label) $opts[] = ['value_attr' => $name, 'label_text' => $label, 'is_selected' => $name === $scope];
    $rows = [];
    foreach ($list['rows'] as $row) {
        $state = $row['annulled'] ? format_time($row['annulled'], 'Y-m-d H:i').' #'.$row['aid'].': '.$row['reason'] : '';
        $dial = $row['annulled'] ? [] : [['href' => $link.'&num='.$list['page'].'&vote='.$row['id'], 'icon_name' => 'x-circle', 'title' => _RATINGS_ANNUL]];
        $rows[] = ['cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $row['id']],
            ['is_col_date' => true, 'moment' => $row['created']],
            ['has_content_text' => true, 'content_text' => $row['scope'].' #'.$row['mid']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => $row['actor']],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $row['value']],
            ['is_truncate' => true, 'has_content_text' => true, 'title_text' => $state, 'content_text' => $state],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'], 'tabs' => [_HOME, _RATINGS_VOTES, _MANUAL], 'tab' => 1,
            'part' => [1], 'subtitle_html' => $tpl->getHtmlFrag('list-filter', [
                'action_url' => $afile.'.php',
                'label' => _SEARCH,
                'is_part' => true,
                'hidden' => [['nameattr' => 'name', 'valueattr' => 'ratings'], ['nameattr' => 'op', 'valueattr' => 'votes']],
                'fields' => [
                    ['field_id' => 'sl-rate-scope', 'label' => _RATINGS_TARGET, 'name_attr' => 'scope', 'options' => $opts, 'is_now' => true],
                    ['field_id' => 'sl-rate-mid', 'label' => _ID, 'name_attr' => 'mid', 'itype' => 'number', 'is_count' => true, 'value' => $mid ? (string)$mid : ''],
                ],
            ])]),
        'note_html' => $note,
        'is_fixed' => true,
        'key' => 'ratings-votes',
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _RATINGS_TARGET],
            ['content' => _RATINGS_ACTOR, 'is_truncate' => true],
            ['content' => _VALUE, 'is_col_count' => true],
            ['content' => _STATUS, 'is_truncate' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $link.'&',
        'page' => $list['page'],
        'pages' => max(1, (int)ceil($list['total'] / $lim)),
        'count' => $list['total'],
        'limit' => $lim,
        'base' => 50,
        'none_text' => $keep ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function annul(): void {
    global $afile;
    $warn = !checkAdminPost('ratings');
    $text = _TOKENMISS;
    if (!$warn) {
        $res = getRatingService()->deleteRating(getVar('post', 'vote', 'num', 0), trim(getVar('post', 'reason', 'raw', '')));
        $texts = ['ok' => _RATINGS_DONE, 'invalid' => _RATINGS_FORM, 'unavailable' => _RATINGS_GONE, 'denied' => _RATINGS_DENY];
        [$warn, $text] = [!$res['ok'], $texts[$res['code']] ?? _RATINGS_FAIL];
    }
    $scope = getVar('post', 'scope', 'raw', '');
    $scope = (is_string($scope) && preg_match('/^(?:account|forum|node\.[a-z0-9_-]+)$/D', $scope)) ? $scope : '';
    $keep = array_filter(['scope' => $scope, 'mid' => getVar('post', 'mid', 'num', 0), 'num' => getVar('post', 'num', 'num', 0)]);
    setRedirect($afile.'.php?name=ratings&op=votes'.($keep ? '&'.http_build_query($keep) : ''), false, 302, $text, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'],
        'tabs' => [_HOME, _RATINGS_VOTES, _MANUAL],
    ]);
}

switch ($op) {
    default: ratings(); break;
    case 'save': save(); break;
    case 'votes': votes(); break;
    case 'annul': annul(); break;
    case 'info': info(); break;
}
