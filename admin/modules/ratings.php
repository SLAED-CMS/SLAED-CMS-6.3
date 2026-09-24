<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function ratings(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'], 'tabs' => [_HOME, _RATINGS_VOTES, _DOCS]]);
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
    $scopes = ['account' => getModuleName('account'), 'forum' => getModuleName('forum'), 'shop' => getModuleName('shop')];
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
    $scopes = array_merge(['account', 'forum', 'shop'], array_map(fn($v) => 'node.'.$v, array_keys($types)));
    if (!$warn) {
        foreach ($scopes as $i => $val) {
            $days = trim((string)getVar('post', 'time['.$i.']', 'raw', ''));
            if (!preg_match('/^(?:0|[1-9][0-9]{0,15})$/D', $days) || intval($days) > intdiv(PHP_INT_MAX, 86400)) $warn = true;
            $content[$val] = ['active' => getVar('post', $i.'in', 'num', 0) ? '1' : '0', 'period' => (string)(intval($days) * 86400)];
            $content[$val] += ['detail' => getVar('post', $i.'view', 'num', 0) ? '1' : '0', 'guests' => getVar('post', $i.'guest', 'num', 0) ? '1' : '0'];
        }
        $text = _RATINGS_BADDAYS;
    }
    $sent = getVar('post', 'scopes', 'raw', '');
    if (!$warn && $sent !== implode(',', $scopes)) {
        $warn = true;
        $seen = explode(',', (string)$sent);
        $text = sprintf(_NODE_STALE, htmlspecialchars(implode(', ', array_merge(array_diff($scopes, $seen), array_diff($seen, $scopes))), ENT_QUOTES, 'UTF-8'));
    }
    if (!$warn) {
        $own = array_filter($content, fn($v) => !str_starts_with($v, 'node.'), ARRAY_FILTER_USE_KEY);
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
    $names = ['account' => getModuleName('account'), 'forum' => getModuleName('forum'), 'shop' => getModuleName('shop')];
    foreach (array_keys(getNodeTypeMap()) as $name) $names['node.'.$name] = getModuleName($name);
    $scope = getVar('get', 'scope', 'raw', '');
    $scope = (is_string($scope) && isset($names[$scope])) ? $scope : '';
    $mid = $scope !== '' ? getVar('get', 'mid', 'num', 0) : 0;
    $after = getVar('get', 'after', 'num', 0);
    $vote = getVar('get', 'vote', 'num', 0);
    $link = $afile.'.php?name=ratings&op=votes'.($scope !== '' ? '&scope='.$scope : '').($mid ? '&mid='.$mid : '');
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'], 'tabs' => [_HOME, _RATINGS_VOTES, _DOCS], 'tab' => 1]);
    if ($vote) {
        $field = $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'reason', 'value_attr' => '', 'maxlength_num' => '255', 'is_config' => true, 'is_required' => true]);
        $cont .= $tpl->getHtmlPart('box', ['title' => _RATINGS_ANNUL.' #'.$vote, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'ratings'],
                ['nameattr' => 'op', 'valueattr' => 'annul'],
                ['nameattr' => 'vote', 'valueattr' => (string)$vote],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('ratings')],
            ],
            'content_html' => $tpl->getHtmlPart('div', ['rows' => [['label_html' => _RATINGS_REASON, 'field_html' => $field]]]),
            'submit_label' => _RATINGS_ANNUL,
        ])]);
    }
    $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $scope === '']];
    foreach ($names as $name => $label) $opts[] = ['value_attr' => $name, 'label_text' => $label, 'is_selected' => $name === $scope];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
        'is_inline_filter' => true,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'ratings'],
            ['nameattr' => 'op', 'valueattr' => 'votes'],
        ],
        'content_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'scope', 'options' => $opts])
            .$tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'mid', 'value_attr' => $mid ? (string)$mid : '', 'placeholder_text' => _RATINGS_TARGET.' '._ID])
            .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]),
    ]);
    $list = getRatingService()->getRatingList($scope, $mid, $after, 50);
    $rows = '';
    foreach ($list['rows'] as $row) {
        $state = $row['annulled'] ? date('Y-m-d H:i', $row['annulled']).' #'.$row['aid'].': '.$row['reason'] : '';
        $href = $link.'&after='.$after.'&vote='.$row['id'];
        $cells = [
            ['is_col_id' => true, 'content_html' => (string)$row['id']],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => date('Y-m-d H:i', $row['created'])],
            ['has_content_text' => true, 'content_text' => $row['scope'].' #'.$row['mid']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => $row['actor']],
            ['is_col_count' => true, 'content_html' => (string)$row['value']],
            ['is_truncate' => true, 'has_content_text' => true, 'title_text' => $state, 'content_text' => $state],
            ['content_html' => $row['annulled'] ? '' : $tpl->getHtmlFrag('link', ['href' => $href, 'title' => _RATINGS_ANNUL, 'label' => _RATINGS_ANNUL])],
        ];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
    }
    if (!$list['ok']) {
        $body = $form.$tpl->getHtmlFrag('alert', ['text' => _RATINGS_FAIL, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    } elseif ($rows === '') {
        $body = $form.$tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    } else {
        $head = [['content' => _ID], ['content' => _DATE], ['content' => _RATINGS_TARGET], ['content' => _RATINGS_ACTOR], ['content' => _VALUE], ['content' => _STATUS]];
        $head[] = ['content' => _FUNCTIONS, 'nosort' => 1];
        $more = count($list['rows']) === 50 ? $tpl->getHtmlFrag('link', ['href' => $link.'&after='.$list['next'], 'title' => _NEXT, 'label' => _NEXT]) : '';
        $body = $form.$tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $rows]).$more;
    }
    echo $cont.$tpl->getHtmlPart('box', ['title' => _RATINGS_VOTES, 'content_html' => $body]);
    setFoot();
}

function annul(): void {
    global $afile;
    $warn = !checkAdminPost('ratings');
    $text = _TOKENMISS;
    if (!$warn) {
        $res = getRatingService()->deleteRating(getVar('post', 'vote', 'num', 0), trim((string)getVar('post', 'reason', 'raw', '')));
        $texts = ['ok' => _RATINGS_DONE, 'invalid' => _RATINGS_FORM, 'unavailable' => _RATINGS_GONE, 'denied' => _RATINGS_DENY];
        [$warn, $text] = [!$res['ok'], $texts[$res['code']] ?? _RATINGS_FAIL];
    }
    setRedirect($afile.'.php?name=ratings&op=votes', false, 302, $text, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=ratings', 'name=ratings&op=votes', 'name=ratings&op=info'],
        'tabs' => [_HOME, _RATINGS_VOTES, _DOCS],
    ]);
}

switch ($op) {
    default: ratings(); break;
    case 'save': save(); break;
    case 'votes': votes(); break;
    case 'annul': annul(); break;
    case 'info': info(); break;
}
