<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('node')) die('Illegal file access');
getLang('node');
require_once BASE_DIR.'/modules/node/index.php';

# The administrative entry of Node: the materials of the types this administrator moderates, and for the manager of Node the types, their import and the limits
# Every write goes through NodeQuery and NodeService with the context of the request; a refusal is answered by its code, a success by a redirect to the canonical page

# Whether the context manages Node and its types: the main administrator or the holder of the right node
function checkNodeManage(): bool {
    $ask = getNodeContext();
    return $ask->super || $ask->manage;
}

# The tabs of the module by operation: the materials for everyone the entry admits, the types, the import and the limits for the manager of Node, then the help
function getNodeAdminOps(): array {
    $ops = ['' => [_HOME, 'name=node'], 'add' => [_ADD, 'name=node&op=add']];
    if (checkNodeManage()) {
        $ops += ['types' => [_NODE_TYPES, 'name=node&op=types'], 'type' => [_NODE_NEWTYPE, 'name=node&op=type'], 'import' => [_NODE_IMPORT, 'name=node&op=import'],
            'config' => [_PREFERENCES, 'name=node&op=config']];
    }
    return $ops + ['info' => [_DOCS, 'name=node&op=info']];
}

# The head of every screen of the module with the tab of the current operation marked
function getNodeAdminTabs(string $cur): string {
    $ops = getNodeAdminOps();
    $tab = array_search($cur, array_keys($ops), true);
    return getTplAdminTabs(['ops' => array_column($ops, 1), 'tabs' => array_column($ops, 0), 'tab' => ($tab === false) ? -1 : $tab]);
}

# Answer a refused request inside the panel with its status and a safe text, and where the refusal asks for it a link to the current record
function setNodeAdminFault(int $code, string $text, string $cur = '', string $href = ''): never {
    global $tpl;
    http_response_code($code);
    setHead();
    $link = ($href !== '') ? $tpl->getHtmlFrag('link', ['href' => $href, 'title' => _NODE_CURRENT, 'label' => _NODE_CURRENT]) : '';
    echo getNodeAdminTabs($cur).$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($text, ENT_QUOTES,
        'UTF-8')]).$link]);
    setFoot();
    exit;
}

# Refuse a request whose method the operation does not answer
function checkNodeMethod(array $allow): void {
    if (in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), $allow, true)) return;
    header('Allow: '.implode(', ', $allow));
    setNodeAdminFault(405, _ERROR);
}

# The label of a state of a material
function getNodeStateLabel(NodeStatus $one): string {
    return match ($one) {
        NodeStatus::Draft => _NODE_SDRAFT,
        NodeStatus::Pending => _NODE_SPEND,
        NodeStatus::Published => _NODE_SPUB,
        NodeStatus::Disabled => _NODE_SOFF,
        NodeStatus::Deleted => _NODE_SDEL,
    };
}

# The label and the icon of the move of a material into one state
function getNodeMoveLabel(NodeStatus $to): array {
    return match ($to) {
        NodeStatus::Draft => [_NODE_TODRAFT, 'file-earmark'],
        NodeStatus::Pending => [_NODE_TOPEND, 'hourglass-split'],
        NodeStatus::Published => [_NODE_TOPUB, 'check2-circle'],
        NodeStatus::Disabled => [_NODE_TOOFF, 'eye-slash'],
        NodeStatus::Deleted => [_NODE_TODEL, 'trash'],
    };
}

# One material of the types this administrator moderates by its global id, with its type; a named type is checked, without one the moderated types are asked in turn
function getNodeAdminItem(int $id, string $name): array {
    if ($id < 1) setNodeAdminFault(404, _NODE_GONE);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    if ($name !== '') $types = isset($types[$name]) ? [$name => $types[$name]] : [];
    foreach ($types as $type) {
        $query = getNodeReader($type);
        if (count($types) > 1 && $query->getNodeContent($id, $type) === null) continue;
        $node = $query->getNode($id, $type);
        if ($node !== null) return [$type, $node];
    }
    setNodeAdminFault(404, _NODE_GONE);
}

# Send the registered author of a moderated material the result of the decision once it is stored: published with its address, or not accepted
function setNodeResultMail(NodeType $type, Node $node, NodeStatus $was): void {
    $done = in_array($node->status, [NodeStatus::Published, NodeStatus::Draft, NodeStatus::Deleted], true);
    if ($was !== NodeStatus::Pending || !$done || !$type->settings['workflow']['notify']['result'] || $node->uid < 1) return;
    $mail = getUserMail($node->uid);
    if ($mail === '') return;
    $url = getPublicUrl(['name' => $type->name, 'op' => 'view', 'id' => $node->id]);
    $text = ($node->status === NodeStatus::Published) ? sprintf(_NODE_MAILPUB, $node->title, $url) : sprintf(_NODE_MAILNO, $node->title);
    if (!addNodeMail([$mail], $node->title, $text)) Logger::addSite('error', 'Node: the result notice could not be queued', ['nid' => $node->id]);
}

# The sections of a stored material that differ from the values a refused form carried, named by their labels; the stored source of an external material is given beside it
function getNodeDiff(NodeType $type, Node $now, array $vals, array $ext = []): array {
    $was = getNodeFormVals($now, $ext);
    $out = [];
    $map = ['title' => _TITLE, 'cid' => _CATEGORY, 'cids' => _NODE_CATS, 'intro' => _NODE_INTRO, 'body' => _TEXT, 'poll' => _VOTING, 'home' => _NODE_FHOME,
        'pinned' => _NODE_FPIN, 'pubdate' => _CHNGSTORY, 'expires' => _ENDDATE, 'rels' => _NODE_RELATED];
    foreach ($map as $key => $label) {
        $one = $vals[$key];
        $two = $was[$key];
        if (is_array($one)) sort($one);
        if (is_array($two)) sort($two);
        if ($key === 'intro' || $key === 'body') [$one, $two] = [str_replace("\r\n", "\n", trim((string)$one)), str_replace("\r\n", "\n", trim((string)$two))];
        if ($key === 'pubdate' || $key === 'expires') [$one, $two] = [substr((string)$one, 0, 16), substr((string)$two, 0, 16)];
        if ($one != $two) $out[] = $label;
    }
    if ($vals['comon'] !== $was['comon']) $out[] = _COMMENTS;
    if ($type->ext === 'sync' && [trim((string)($vals['ext']['url'] ?? '')), (string)($vals['ext']['refresh'] ?? '')] !== [(string)($ext['url'] ?? ''),
        (string)($ext['refresh'] ?? '')]) $out[] = _NODE_SOURCE;
    $fields = $vals['fields'];
    foreach ($type->fields as $key => $def) {
        if ($def['active'] && (string)json_encode($fields[$key] ?? null) !== (string)json_encode($was['fields'][$key] ?? null)) $out[] = getConst($def['title']);
    }
    $keep = fn(array $list): array => array_map(fn(array $v): array => [$v['role'], $v['src'], $v['title']],
        array_values(array_filter($list, fn(array $v): bool => $v['src'] !== '')));
    if ($keep($vals['assets']) !== $keep($was['assets'])) $out[] = _NODE_ASSETS;
    return $out;
}

# The administrative form of one material: the shared rows of the module, then the rights only a moderator has, the state of a new material and the version of a stored one
function getNodeAdminForm(NodeType $type, array $vals, array $errs, int $id, int $ver, NodeStatus $state, bool $clash = false): string {
    global $afile, $tpl;
    $feat = $type->settings['features'];
    $rows = [];
    foreach (getNodeFormRows($type, $vals, $errs, true, true) as $one) {
        $rows[] = ['label_for' => $one['for'] ?? '', 'label_html' => htmlspecialchars($one['label'], ENT_QUOTES, 'UTF-8'),
            'hint_html' => htmlspecialchars($one['hint'] ?? '', ENT_QUOTES, 'UTF-8'), 'hint_id' => $one['hint_id'] ?? '', 'field_html' => $one['field'],
                'is_full' => !empty($one['full'])];
    }
    if ($feat['comments']) {
        $opts = '';
        foreach ([CommentMode::Disabled->value => _DEACTIVATE, CommentMode::Moderated->value => _APOSTMOD, CommentMode::Open->value => _APOSTNOMOD] as $key => $label) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $vals['comon']->value === $key]);
        }
        $rows[] = ['label_for' => 'f-comon', 'label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'comon', 'selectid' => 'f-comon',
            'options_html' => $opts])];
    }
    if ($feat['poll']) {
        $rows[] = ['label_for' => 'f-poll', 'label_html' => _VOTING, 'hint_html' => _NODE_POLLHINT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number',
            'name_attr' => 'poll',
            'input_id' => 'f-poll', 'value_attr' => $vals['poll'] ? (string)$vals['poll'] : ''])];
    }
    foreach (['home' => _NODE_FHOME, 'pinned' => _NODE_FPIN] as $key => $label) {
        if (!$feat[$key]) continue;
        $rows[] = ['label_html' => $label, 'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => $key, 'value_attr' => '1', 'input_id' => 'f-'.$key,
            'is_checked' => $vals[$key]])];
    }
    $date = fn(?string $val): string => ($val === null) ? '' : str_replace(' ', 'T', substr($val, 0, 16));
    $rows[] = ['label_for' => 'f-pubdate', 'label_html' => _CHNGSTORY, 'hint_html' => _NODE_DATEHINT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'datetime-local',
        'name_attr' => 'pubdate', 'input_id' => 'f-pubdate', 'value_attr' => $date($vals['pubdate'])])];
    if ($feat['schedule']) {
        $rows[] = ['label_for' => 'f-expires', 'label_html' => _ENDDATE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'datetime-local', 'name_attr' => 'expires',
            'input_id' => 'f-expires', 'value_attr' => $date($vals['expires'])])];
    }
    $hidden = [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => $id ? 'edit' : 'add'], ['name_attr' => 'type', 'value_attr' => $type->name],
        ['name_attr' => 'token', 'value_attr' => getSiteToken('node')]];
    if ($id) {
        $hidden[] = ['name_attr' => 'id', 'value_attr' => (string)$id];
        $hidden[] = ['name_attr' => 'version', 'value_attr' => (string)$ver];
        $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'keep', 'label_text' => _NODE_KEEP, 'is_selected' => true])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES]);
        $act = $clash ? $tpl->getHtmlFrag('select', ['name_attr' => 'action', 'options_html' => $opts, 'is_inline_gap' => true])
            : $tpl->getHtmlFrag('hidden', ['name_attr' => 'action', 'value_attr' => 'save', 'input_attr' => '']);
    } else {
        $opts = '';
        foreach ([NodeStatus::Draft, NodeStatus::Pending, NodeStatus::Published] as $one) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$one->value, 'label_text' => getNodeStateLabel($one), 'is_selected' => $one === $state]);
        }
        $act = $tpl->getHtmlFrag('select', ['name_attr' => 'status', 'options_html' => $opts, 'is_inline_gap' => true]);
    }
    return $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'form_attr' => 'enctype="multipart/form-data"',
        'hidden' => $hidden,
        'rows' => $rows,
        'actions_html' => $act.$tpl->getHtmlFrag('button', ['submit_label' => $clash ? _OK : _SAVECHANGES, 'button_type' => 'submit']),
    ])]);
}

# The open reports of the resources of a stored material with the two explicit decisions of its moderator
function getNodeReportRows(NodeType $type, Node $node): string {
    global $tpl;
    $out = '';
    foreach ($node->assets ?? [] as $one) {
        if ($one->reported === null) continue;
        $name = ($one->title !== '') ? $one->title : (($one->name !== '') ? $one->name : basename($one->src));
        $base = ['name' => 'node', 'op' => 'report', 'id' => $one->id, 'type' => $type->name];
        $dial = [getTplPostAction($base + ['useful' => 1], 'check2-circle', _NODE_USEFUL), getTplPostAction($base + ['useful' => 0], 'x-circle', _NODE_REJECT)];
        $out .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_title' => true, 'has_content_text' => true, 'content_text' => $name],
            ['content_html' => htmlspecialchars(format_time($one->reported, _TIMESTRING), ENT_QUOTES, 'UTF-8')],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]])]);
    }
    if ($out === '') return '';
    return $tpl->getHtmlPart('box', ['title' => _NODE_REPORTS, 'content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'head' => [
        ['content' => _NODE_ASSETS, 'is_col_title' => true], ['content' => _DATE], ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
    ], 'rows_html' => $out])]);
}

# The options of one select of the working card or of the queue filter: a choice for all where asked, then value => label with the picked one marked
function getNodeSupportOpts(array $list, ?int $pick, string $all = ''): string {
    global $tpl;
    $out = ($all !== '') ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'all', 'label_text' => $all, 'is_selected' => $pick === null]) : '';
    foreach ($list as $key => $label) $out .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $pick === $key]);
    return $out;
}

# One filter of the queue from the query: all for the word all, a whole number for digits, the default for an absent value; anything else is a bad request
function getNodeSupportFilter(string $key, ?int $def): ?int {
    $raw = (string)getVar('get', $key, 'raw', '');
    if ($raw === '') return $def;
    if ($raw === 'all') return null;
    if (!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', $raw)) setNodeAdminFault(400, _NODE_INVALID);
    return (int)$raw;
}

# The queue of a support type: the requests waiting for support by priority and age unless the filter of state, assignment and priority asks for others
function setNodeSupportQueue(NodeType $type): void {
    global $afile, $conf, $tpl;
    $hand = getNodeHandler($type);
    $state = getNodeSupportFilter('state', (int)($conf['node']['support']['state']['staff'] ?? 0));
    $aid = getNodeSupportFilter('aid', null);
    $prio = getNodeSupportFilter('prio', null);
    $num = max(1, getVar('get', 'num', 'num', 1));
    $lim = $type->settings['list']['limit'];
    try {
        $data = $hand->getNodeSupportList($type, $num, $lim, $state, $aid, $prio);
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeFault($err));
    }
    $labs = getNodeSupportLabels();
    $who = getAdminNames('node-'.$type->name);
    setHead();
    $cont = getNodeAdminTabs('');
    $cont .= $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
        'hidden' => [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'type', 'value_attr' => $type->name]],
        'content_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'state', 'options_html' => getNodeSupportOpts($labs['state'], $state, _ALL), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('select', ['name_attr' => 'prio', 'options_html' => getNodeSupportOpts($labs['prio'], $prio, _ALL), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('select', ['name_attr' => 'aid', 'options_html' => getNodeSupportOpts([0 => _NODE_NOBODY] + $who, $aid, _ALL), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    $rows = '';
    foreach ($data['nodes'] as $one) {
        $card = $data['ext'][$one->id];
        $dial = [['href' => $afile.'.php?name=node&op=support&id='.$one->id, 'icon_name' => 'kanban', 'title' => _NODE_CARD],
            ['href' => getSeoUrl(['name' => $type->name, 'op' => 'view', 'id' => $one->id, 'title' => $one->title]), 'icon_name' => 'eye', 'title' => _MVIEW]];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_id' => true, 'content_html' => (string)$one->id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $one->title, 'has_content_text' => true, 'content_text' => $one->title],
            ['is_col_author' => true, 'has_content_text' => true, 'content_text' => ($card['uname'] !== '') ? $card['uname'] : _ANONYM],
            ['has_content_text' => true, 'content_text' => $labs['state'][$card['state']] ?? ''],
            ['has_content_text' => true, 'content_text' => $labs['prio'][$card['prio']] ?? ''],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => ($card['aname'] !== '') ? $card['aname'] : _NODE_NOBODY],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time($card['activity'], _TIMESTRING)],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]])]);
    }
    if ($rows === '') {
        $cont .= $tpl->getHtmlPart('box', ['title' => _NODE_QUEUE, 'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    } else {
        $head = [['content' => _ID, 'is_col_id' => true], ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true], ['content' => _POSTEDBY, 'is_col_author' => true],
            ['content' => _NODE_STATE], ['content' => _NODE_PRIO], ['content' => _NODE_ASSIGN, 'is_truncate' => true], ['content' => _NODE_LAST, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]];
        $keep = ['state' => ($state === null) ? 'all' : $state, 'aid' => ($aid === null) ? 'all' : $aid, 'prio' => ($prio === null) ? 'all' : $prio];
        $link = static fn(int $i): array => ['href' => $afile.'.php?name=node&type='.$type->name.'&'.http_build_query($keep).'&num='.$i];
        $pages = max(1, (int)ceil($data['count'] / $lim));
        $body = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows])
            .getTplPagerView($num, $pages, 8, $link, ['count' => $data['count'], 'limit' => $lim]);
        $cont .= $tpl->getHtmlPart('box', ['title' => _NODE_QUEUE, 'content_html' => $body]);
    }
    echo $cont;
    setFoot();
}

function show(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD']);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    $name = (string)getVar('get', 'type', 'var', '');
    if ($name !== '' && !isset($types[$name])) setNodeAdminFault(404, _NODE_GONE);
    if ($name !== '' && $types[$name]->ext === 'support') {
        setNodeSupportQueue($types[$name]);
        return;
    }
    $raw = (string)getVar('get', 'status', 'raw', '');
    $state = ($raw === '') ? NodeStatus::Published : (ctype_digit($raw) ? NodeStatus::tryFrom((int)$raw) : null);
    if ($state === null) setNodeAdminFault(400, _NODE_INVALID);
    $num = max(1, getVar('get', 'num', 'num', 1));
    setHead();
    $cont = getNodeAdminTabs('');
    $topts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $name === '']);
    foreach ($types as $key => $one) $topts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $key, 'label_text' => getModuleName($key), 'is_selected' => $key === $name]);
    $sopts = '';
    foreach (NodeStatus::cases() as $one) $sopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$one->value, 'label_text' => getNodeStateLabel($one),
        'is_selected' => $one === $state]);
    $cont .= $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
        'hidden' => [['name_attr' => 'name', 'value_attr' => 'node']],
        'content_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'type', 'options_html' => $topts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('select', ['name_attr' => 'status', 'options_html' => $sopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    if (!$types) {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NODE_NOTYPES])]);
        echo $cont;
        setFoot();
        return;
    }
    $pick = ($name !== '') ? [$types[$name]] : array_values($types);
    $lim = min(array_map(fn(NodeType $v): int => $v->settings['list']['limit'], $pick));
    $query = getNodeReader((count($pick) === 1) ? $pick[0] : null);
    if (count($pick) === 1) $query->setNodeType($pick[0]);
    else $query->setNodeTypes($pick);
    $query->setNodeStatus($state)->setNodeSets(false)->setNodePage($num, $lim);
    $count = $query->getNodeCount();
    $pages = max(1, (int)ceil($count / $lim));
    $rows = '';
    $tmap = [];
    foreach ($types as $one) $tmap[$one->id] = $one;
    foreach ($count ? $query->getNodeList() : [] as $node) {
        $type = $tmap[$node->tid];
        $dial = [['href' => getSeoUrl(['name' => $type->name, 'op' => 'view', 'id' => $node->id, 'title' => $node->title]), 'icon_name' => 'eye', 'title' => _MVIEW],
            ['href' => $afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name, 'icon_name' => 'pencil', 'title' => _FULLEDIT]];
        foreach (NodeStatus::cases() as $to) {
            if (!$node->status->checkStatusMove($to)) continue;
            [$label, $icon] = getNodeMoveLabel($to);
            $dial[] = getTplPostAction(['name' => 'node', 'op' => 'status', 'id' => $node->id, 'type' => $type->name, 'status' => $to->value, 'version' => $node->version],
                $icon, $label);
        }
        $dial[] = getTplPostAction(['name' => 'node', 'op' => 'delete', 'id' => $node->id, 'type' => $type->name, 'version' => $node->version], 'x-octagon', _DELETE,
            _DELETE.' "'.$node->title.'"?');
        $who = ($node->uid > 0) ? (string)$node->uname : (($node->aname !== '') ? $node->aname : _ANONYM);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_id' => true, 'content_html' => (string)$node->id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $node->title, 'has_content_text' => true, 'content_text' => $node->title],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => getModuleName($type->name)],
            ['has_content_text' => true, 'content_text' => getNodeStateLabel($node->status)],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => ($node->pubdate !== null) ? format_time($node->pubdate, _TIMESTRING) : ''],
            ['is_col_author' => true, 'has_content_text' => true, 'content_text' => $who],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]])]);
    }
    if ($rows === '') {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    } else {
        $head = [['content' => _ID, 'is_col_id' => true], ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true], ['content' => _NODE_TYPE,
            'is_truncate' => true], ['content' => _STATUS], ['content' => _DATE, 'is_col_date' => true], ['content' => _POSTEDBY, 'is_col_author' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]];
        $link = static fn(int $i): array => ['href' => $afile.'.php?name=node'.(($name !== '') ? '&type='.$name : '').'&status='.$state->value.'&num='.$i];
        $body = $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows])
            .getTplPagerView($num, $pages, 8, $link, ['count' => $count, 'limit' => $lim]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    $name = (string)getVar('req', 'type', 'var', '');
    if ($name === '') {
        setHead();
        $cont = getNodeAdminTabs('add');
        $items = '';
        foreach ($types as $key => $one) {
            $items .= $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=node&op=add&type='.$key, 'title' => getModuleName($key), 'label' => getModuleName($key),
                'is_line_break' => true]);
        }
        $cont .= $tpl->getHtmlPart('box', ['title' => _NODE_PICK, 'content_html' => ($items !== '') ? $items : $tpl->getHtmlFrag('alert', ['is_warn' => false,
            'text' => _NODE_NOTYPES])]);
        echo $cont;
        setFoot();
        return;
    }
    $type = $types[$name] ?? setNodeAdminFault(404, _NODE_GONE, 'add');
    $vals = getNodeFormVals(null);
    $state = NodeStatus::Draft;
    $errs = [];
    $note = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'add');
        $state = NodeStatus::tryFrom(getVar('post', 'status', 'num', 0)) ?? NodeStatus::Draft;
        [$input, $vals, $bad] = getNodeFormPost($type, true, null);
        if ($bad) {
            $note = implode(' ', $bad);
            http_response_code(422);
        } else {
            try {
                $node = getNodeWriter($type)->addNode($type, $input, $state);
                setRedirect($afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name, false, 302, _NODE_CREATED);
            } catch (NodeException $err) {
                $note = getNodeFault($err);
                $errs = getNodeFieldErrors($err);
                http_response_code(getNodeStatus($err));
            }
        }
    }
    setHead();
    echo getNodeAdminTabs('add')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .getNodeAdminForm($type, $vals, $errs, 0, 0, $state);
    setFoot();
}

function edit(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    [$type, $node] = getNodeAdminItem(getVar('req', 'id', 'num', 0), (string)getVar('req', 'type', 'var', ''));
    $card = ($type->ext === 'sync') ? (getNodeHandler($type)->getNodeData($type, [$node], 'admin')[$node->id] ?? []) : [];
    $ext = $card ? ['url' => $card['url'], 'refresh' => $card['refresh']] : [];
    $vals = getNodeFormVals($node, $ext);
    $ver = $node->version;
    $errs = [];
    $note = '';
    $clash = false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS);
        $act = (string)getVar('post', 'action', 'var', 'save');
        $sent = getVar('post', 'version', 'num', 0);
        [$input, $vals, $bad] = getNodeFormPost($type, true, $node);
        if ($act === 'keep') {
            $ver = $node->version;
        } elseif ($bad) {
            $ver = $sent;
            $note = implode(' ', $bad);
            http_response_code(422);
        } else {
            try {
                getNodeWriter($type)->updateNode($node->id, $input, $sent);
                setRedirect($afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name, false, 302, _NODE_SAVED);
            } catch (NodeException $err) {
                $ver = $sent;
                $note = getNodeFault($err);
                $errs = getNodeFieldErrors($err);
                http_response_code(getNodeStatus($err));
                if ($err->getCode() === NodeException::CONFLICT) {
                    $list = getNodeDiff($type, $node, $vals, $ext);
                    $note = sprintf(_NODE_CONFLICT, $list ? implode(', ', $list) : '-');
                    $clash = true;
                }
            }
        }
    }
    setHead();
    $open = $clash ? $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name, 'title' => _NODE_CURRENT,
        'label' => _NODE_CURRENT, 'is_line_break' => true]) : '';
    echo getNodeAdminTabs('')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .$open
        .getNodeReportRows($type, $node)
        .getNodeAdminForm($type, $vals, $errs, $node->id, $ver, $node->status, $clash);
    if ($card) {
        $when = fn(?string $val): string => ($val === null) ? _NO : format_time($val, _TIMESTRING);
        $rows = [
            ['label_html' => _URL, 'field_html' => htmlspecialchars($card['url'], ENT_QUOTES, 'UTF-8')],
            ['label_html' => _NODE_PERIOD, 'field_html' => $card['refresh'] ? $card['refresh'].' '._SEC : _NODE_MANUAL],
            ['label_html' => _NODE_DUE, 'field_html' => htmlspecialchars($when($card['due']), ENT_QUOTES, 'UTF-8')],
            ['label_html' => _NODE_CHECKED, 'field_html' => htmlspecialchars($when($card['checked']), ENT_QUOTES, 'UTF-8')],
            ['label_html' => _NODE_SYNCED, 'field_html' => htmlspecialchars($when($card['synced']), ENT_QUOTES, 'UTF-8')],
            ['label_html' => _NODE_FAILS, 'field_html' => (string)$card['fails']],
            ['label_html' => _ERROR, 'field_html' => ($card['error'] !== '') ? htmlspecialchars($card['error'], ENT_QUOTES, 'UTF-8') : _NO],
        ];
        $hidden = [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'sync'], ['name_attr' => 'id', 'value_attr' => (string)$node->id],
            ['name_attr' => 'type', 'value_attr' => $type->name], ['name_attr' => 'token', 'value_attr' => getSiteToken('node')]];
        echo $tpl->getHtmlPart('box', ['title' => _NODE_SOURCE, 'content_html' => $tpl->getHtmlPart('form', ['action_url' => $afile.'.php', 'hidden' => $hidden,
            'rows' => $rows, 'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _NODE_SYNCGO, 'button_type' => 'submit'])])]);
    }
    setFoot();
}

function sync(): void {
    global $afile;
    checkNodeMethod(['POST']);
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS);
    [$type, $node] = getNodeAdminItem(getVar('post', 'id', 'num', 0), (string)getVar('post', 'type', 'var', ''));
    $hand = getNodeHandler($type);
    if (!$hand instanceof NodeSync) setNodeAdminFault(404, _NODE_GONE);
    $self = $afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name;
    try {
        $res = $hand->updateNodeSync($node->id);
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeFault($err), '', $self);
    }
    if ($res['status'] === 'failed') setNodeAdminFault(502, sprintf(_NODE_SYNCERR, $res['error']), '', $self);
    $text = ['updated' => _NODE_SYNCNEW, 'unchanged' => _NODE_SYNCOK][$res['status']] ?? _NODE_SYNCSKIP;
    setRedirect($self, false, 302, $text, $res['status'] === 'skipped');
}

# Answer a refused change of state or deletion: a stale version asks for the current record to be opened and the action to be confirmed again
function setNodeActFault(NodeException $err, NodeType $type, int $id): never {
    global $afile;
    $text = ($err->getCode() === NodeException::CONFLICT) ? _NODE_AGAIN : getNodeFault($err);
    if ($err->getCode() === NodeException::INVALID && preg_match('/: ((?:fields|assets)\.[a-z0-9_.]+)$/D', $err->getMessage(), $hit)) $text .= ' ('.$hit[1].')';
    setNodeAdminFault(getNodeStatus($err), $text, '', $afile.'.php?name=node&op=edit&id='.$id.'&type='.$type->name);
}

function status(): void {
    global $afile;
    checkNodeMethod(['POST']);
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS);
    $id = getVar('post', 'id', 'num', 0);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    $type = $types[(string)getVar('post', 'type', 'var', '')] ?? setNodeAdminFault(404, _NODE_GONE);
    $raw = (string)getVar('post', 'status', 'raw', '');
    $to = ctype_digit($raw) ? (NodeStatus::tryFrom((int)$raw) ?? setNodeAdminFault(422, _NODE_INVALID)) : setNodeAdminFault(422, _NODE_INVALID);
    $was = getNodeReader($type)->getNodeContent($id, $type) ?? setNodeAdminFault(404, _NODE_GONE);
    try {
        $node = getNodeWriter($type)->updateNodeStatus($id, $to, getVar('post', 'version', 'num', 0));
    } catch (NodeException $err) {
        setNodeActFault($err, $type, $id);
    }
    setNodeResultMail($type, $node, $was->status);
    setRedirect($afile.'.php?name=node&type='.$type->name.'&status='.$was->status->value, true, 302, _NODE_MOVED);
}

function delete(): void {
    global $afile, $com;
    checkNodeMethod(['POST']);
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS);
    $id = getVar('post', 'id', 'num', 0);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    $type = $types[(string)getVar('post', 'type', 'var', '')] ?? setNodeAdminFault(404, _NODE_GONE);
    getNodeReader($type)->getNodeContent($id, $type) ?? setNodeAdminFault(404, _NODE_GONE);
    try {
        getNodeWriter($type)->deleteNode($id, getVar('post', 'version', 'num', 0), $com);
    } catch (NodeException $err) {
        setNodeActFault($err, $type, $id);
    }
    setRedirect($afile.'.php?name=node&type='.$type->name, true, 302, _NODE_REMOVED);
}

function report(): void {
    global $afile;
    checkNodeMethod(['POST']);
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS);
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => checkNodeModer($v));
    $type = $types[(string)getVar('post', 'type', 'var', '')] ?? setNodeAdminFault(404, _NODE_GONE);
    $useful = (string)getVar('post', 'useful', 'raw', '');
    if (!in_array($useful, ['0', '1'], true)) setNodeAdminFault(422, _NODE_INVALID);
    try {
        getNodeWriter($type)->deleteNodeAssetReport(getVar('post', 'id', 'num', 0), $type, $useful === '1');
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeFault($err));
    }
    setRedirect($afile.'.php?name=node', true, 302, _NODE_DECIDED);
}

# Refuse every type screen to a context that neither manages Node nor is the main administrator
function checkNodeTypes(string $cur): void {
    if (!checkNodeManage()) setNodeAdminFault(403, _ACCESSDENIED, $cur);
}

# The safe text of a refused type operation: the version was stale, or the type refused the change with the path of the first error, or the storage failed
function getNodeTypeFault(NodeException $err, string $name): string {
    $label = ($name !== '') ? $name : '-';
    if ($err->getCode() === NodeException::CONFLICT) return sprintf(_NODE_STALE, $label);
    if ($err->getCode() === NodeException::INVALID) {
        $path = preg_match('/^Invalid node input: (.+)$/D', $err->getMessage(), $hit) ? $hit[1] : '';
        if ($path === 'directory') return sprintf(_NODE_TGUARD, $label);
        if ($path === 'remains') return sprintf(_NODE_BAD, $label).' '._NODE_REMHINT;
        return sprintf(_NODE_BAD, $label).(($path !== '') ? ' ('.$path.')' : '');
    }
    if ($err->getCode() === NodeException::STORAGE && getConfigJournal()) return _CONFIG_PENDING;
    return getNodeFault($err);
}

function types(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD']);
    checkNodeTypes('types');
    setHead();
    $cont = getNodeAdminTabs('types');
    $rows = '';
    foreach (getNodeTypeMap() as $name => $type) {
        $base = ['name' => 'node', 'type' => $name, 'version' => $type->version];
        $dial = [['href' => $afile.'.php?name=node&op=type&type='.$name, 'icon_name' => 'pencil', 'title' => _FULLEDIT]];
        if ($type->active) $dial[] = ['href' => getSeoUrl(['name' => $name]), 'icon_name' => 'arrow-up-right-circle', 'title' => _VIEWSITE];
        if ($type->settings['features']['categories']) $dial[] = ['href' => $afile.'.php?name=categories&modul='.$name, 'icon_name' => 'folder2', 'title' => _CATEGORIES];
        $dial[] = ['href' => $afile.'.php?name=fields', 'icon_name' => 'plus-square-dotted', 'title' => _NODE_FIELDS];
        $dial[] = ['href' => $afile.'.php?name=node&op=clone&type='.$name, 'icon_name' => 'files', 'title' => _NODE_CLONE];
        $dial[] = ['href' => $afile.'.php?name=node&op=export&type='.$name, 'icon_name' => 'download', 'title' => _NODE_EXPORT];
        $dial[] = getTplPostAction($base + ['op' => 'typestatus', 'active' => $type->active ? 0 : 1], 'power', $type->active ? _DEACTIVATE : _ACTIVATE);
        $dial[] = getTplPostAction($base + ['op' => 'typedelete'], 'x-octagon', _DELETE, _DELETE.' "'.$name.'"?');
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_id' => true, 'content_html' => (string)$type->id],
            ['has_content_text' => true, 'content_text' => $name],
            ['is_col_title' => true, 'is_truncate' => true, 'has_content_text' => true, 'content_text' => getModuleName($name)],
            ['has_content_text' => true, 'content_text' => $type->settings['view']['mode']],
            ['has_content_text' => true, 'content_text' => (string)$type->version],
            ['is_col_status' => true, 'content_html' => ad_status('', $type->active ? '1' : '0')],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]])]);
    }
    if ($rows === '') {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NODE_NOTYPES])]);
    } else {
        $head = [['content' => _ID, 'is_col_id' => true], ['content' => _NODE_NAME], ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true],
            ['content' => _NODE_MODE], ['content' => _NODE_VER], ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]];
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows])]);
    }
    try {
        $gone = getNodeWriter()->getNodeRemains();
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeTypeFault($err, ''), 'types');
    }
    $rows = '';
    foreach ($gone as $name => $num) {
        $dial = [getTplPostAction(['name' => 'node', 'op' => 'remains', 'modul' => $name], 'x-octagon', _DELETE, _DELETE.' "'.$name.'"?')];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_title' => true, 'has_content_text' => true, 'content_text' => $name],
            ['has_content_text' => true, 'content_text' => (string)$num['comments']],
            ['has_content_text' => true, 'content_text' => (string)$num['favorites']],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial])],
        ]])]);
    }
    if ($rows !== '') {
        $head = [['content' => _NODE_NAME, 'is_col_title' => true], ['content' => _COMMENTS], ['content' => _FAVORITES],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]];
        $cont .= $tpl->getHtmlPart('box', ['title' => _NODE_REMAINS, 'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NODE_REMHINT])
            .$tpl->getHtmlFrag('table', ['is_wrapless' => true, 'is_fixed' => true, 'head' => $head, 'rows_html' => $rows])]);
    }
    echo $cont;
    setFoot();
}

# Delete the comments and favorites a removed section left under one module key, so a type may be registered under that name again
function remains(): void {
    global $afile;
    checkNodeMethod(['POST']);
    checkNodeTypes('types');
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'types');
    $name = (string)getVar('post', 'modul', 'var', '');
    try {
        getNodeWriter()->deleteNodeRemains($name);
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeTypeFault($err, $name), 'types');
    }
    setRedirect($afile.'.php?name=node&op=types', false, 302, _NODE_REMGONE);
}

# The export of a shipped profile of modules/node/profiles by its name, decoded, or null when no such profile ships
function getNodeProfile(string $name): ?array {
    if (!preg_match('/^[a-z][a-z0-9]{0,19}$/D', $name)) return null;
    $file = BASE_DIR.'/modules/node/profiles/'.$name.'.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return (is_array($data) && is_array($data['type'] ?? null)) ? $data : null;
}

# The effective settings a new type starts from: the shared defaults with every switch of the features off and no role
function getNodeTypeBase(): array {
    global $conf;
    $base = $conf['node']['defaults'] ?? [];
    $base['features'] = array_fill_keys(['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related', 'tree'], false);
    $base['assets'] = [];
    $base['form'] = [];
    $base['admin'] = [];
    $base['ext'] = [];
    return $base;
}

# The values the type form shows: a stored type, a shipped profile or the defaults of a new type
function getNodeTypeVals(?NodeType $type, ?array $prof): array {
    $def = $prof['type'] ?? null;
    $set = $type?->settings ?? getNodeTypeBase();
    if ($def !== null) {
        foreach ((array)$def['settings'] as $key => $val) if (is_array($val) && isset($set[$key]) && is_array($set[$key])) $set[$key] = array_replace($set[$key], $val);
    }
    return [
        'name' => $type?->name ?? (string)($def['name'] ?? ''),
        'title' => $type?->title ?? (string)($def['title'] ?? ''),
        'intro' => $type?->intro ?? (string)($def['intro'] ?? ''),
        'ext' => $type?->ext ?? (string)($def['ext'] ?? ''),
        'sort' => $type?->sort ?? (int)($def['sort'] ?? 0),
        'set' => $set,
    ];
}

# Read the posted type form into the effective settings and the input the service takes; fields, uploads and rating stay those of the stored type or the defaults,
# and the extension settings those of the stored type or of the shipped profile the new type starts from
# A public workflow for guests without moderation is taken only with its explicit confirmation, because every visitor would then publish directly
function getNodeTypePost(?NodeType $old, ?array $prof): array {
    $num = fn(string $key, int $def): int => (($v = getVar('post', $key, 'raw', '')) !== '' && is_string($v) && preg_match('/^-?[0-9]{1,18}$/D', trim($v))) ? (int)trim($v) : $def;
    $flag = fn(string $key): bool => (string)getVar('post', $key, 'raw', '') === '1';
    $pick = fn(string $key, array $allow): array => array_values(array_intersect($allow, (array)getVar('post', $key.'[]', '', [])));
    $who = (array)getVar('post', 'access[]', '', []);
    $gids = [];
    foreach ($who as $one) if (is_string($one) && preg_match('/^2\|([1-9][0-9]{0,9})$/D', $one, $hit)) $gids[] = (int)$hit[1];
    $access = in_array('0|0', $who, true) ? 'all' : ($gids ? 'group' : 'user');
    $pubs = [];
    foreach ((array)getVar('post', 'publish[]', '', []) as $one) if (is_string($one) && preg_match('/^2\|([1-9][0-9]{0,9})$/D', $one, $hit)) $pubs[] = (int)$hit[1];
    $feats = [];
    foreach (array_keys(getNodeTypeBase()['features']) as $key) $feats[$key] = $flag('feat_'.$key);
    $roles = [];
    foreach ((array)getVar('post', 'role[]', '', []) as $row) {
        if (!is_array($row)) continue;
        $key = trim((string)($row['name'] ?? ''));
        if ($key === '') continue;
        $exts = array_values(array_filter(array_map('trim', explode(',', strtolower((string)($row['extensions'] ?? ''))))));
        $size = trim((string)($row['maxbytes'] ?? ''));
        $roles[$key] = [
            'title' => trim((string)($row['title'] ?? '')),
            'intro' => trim((string)($row['intro'] ?? '')),
            'kinds' => array_values(array_intersect(NodeQuery::KINDS, (array)($row['kinds'] ?? []))),
            'extensions' => $exts,
            'maxbytes' => ctype_digit($size) ? (int)$size : null,
            'min' => (int)($row['min'] ?? 0),
            'max' => (int)($row['max'] ?? 1),
            'canlink' => ($row['canlink'] ?? '') === '1',
            'report' => ($row['report'] ?? '') === '1',
            'mode' => (string)($row['mode'] ?? ''),
            'active' => ($row['active'] ?? '') === '1',
            'sort' => (int)($row['sort'] ?? 0),
        ];
    }
    $set = [
        'list' => ['orders' => $pick('orders', ['published', 'updated', 'title', 'views', 'rating']), 'order' => (string)getVar('post', 'order', 'var', 'published'),
            'dir' => (string)getVar('post', 'dir', 'var', 'desc'), 'limit' => $num('limit', 10), 'alpha' => $flag('alpha'), 'show' => $pick('show', ['category', 'author', 'date',
                'views'])],
        'view' => ['mode' => trim((string)getVar('post', 'mode', 'raw', 'default'))],
        'form' => [],
        'workflow' => ['access' => $access, 'groups' => $gids, 'publish' => $pubs, 'notify' => ['pending' => $flag('notify_pending'), 'result' => $flag('notify_result')]],
        'admin' => [],
        'features' => $feats,
        'assets' => $roles,
        'integrations' => ['search' => $flag('integ_search'), 'rss' => $flag('integ_rss'), 'sitemap' => $flag('integ_sitemap'), 'blocks' => $flag('integ_blocks'),
            'seo' => (string)getVar('post', 'seo', 'var', 'website')],
        'ext' => $old?->settings['ext'] ?? (array)($prof['type']['settings']['ext'] ?? []),
    ];
    $vals = ['name' => trim((string)getVar('post', 'tname', 'raw', '')), 'title' => trim((string)getVar('post', 'title', 'raw', '')), 'intro' => trim((string)getVar('post',
        'intro', 'raw', '')),
        'ext' => trim((string)getVar('post', 'ext', 'raw', '')), 'sort' => $num('sort', 0), 'set' => $set];
    $errs = ($access === 'all' && $feats['submit'] && !$feats['moderation'] && !$flag('guestok')) ? [_NODE_GUESTNO] : [];
    $input = new NodeTypeInput($vals['title'], $vals['intro'], $vals['ext'], $vals['sort'], $set, $old?->fields ?? [], $old?->uploads ?? [], $old?->rating ?? []);
    return [$input, $vals, $errs];
}

# The settings sections of a stored type that differ from the values a refused type form carried
function getNodeTypeDiff(NodeType $now, array $vals): array {
    $out = [];
    foreach (['title' => _TITLE, 'intro' => _DESCRIPTION, 'ext' => _NODE_EXT, 'sort' => _SORT] as $key => $label) if ((string)$vals[$key] !== (string)$now->$key) $out[] = $label;
    $map = ['list' => _NODE_SECLIST, 'view' => _NODE_MODE, 'workflow' => _NODE_FLOW, 'features' => _NODE_FEATURES, 'assets' => _NODE_ROLES, 'integrations' => _NODE_INTEG];
    foreach ($map as $key => $label) if (json_encode($vals['set'][$key]) !== json_encode($now->settings[$key])) $out[] = $label;
    return $out;
}

# The rows of the type form: identity, the list, the display, the workflow, the features, the roles of resources and the integrations
function getNodeTypeRows(array $vals, bool $new): array {
    global $tpl;
    $set = $vals['set'];
    $esc = fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $box = fn(string $name, bool $on, string $label): string => $tpl->getHtmlFrag('checkbox', ['name_attr' => $name, 'value_attr' => '1', 'is_checked' => $on,
        'label_text' => $label]);
    $opts = fn(array $list, string|array $cur): string => implode('', array_map(fn(int|string $k, string $v): string => $tpl->getHtmlFrag('select-option', [
        'value_attr' => (string)$k, 'label_text' => $v, 'is_selected' => is_array($cur) ? in_array((string)$k, $cur, true) : (string)$k === (string)$cur]), array_keys($list), $list));
    $head = fn(string $label): array => ['label_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => $label]), 'field_html' => '', 'is_full' => true];
    $rows = [];
    $rows[] = ['label_for' => 'f-tname', 'label_html' => _NODE_NAME, 'hint_html' => $esc(_NODE_NAMEHINT), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text',
        'name_attr' => 'tname', 'input_id' => 'f-tname', 'value_attr' => $vals['name'], 'maxlength_num' => 20, 'is_required' => true, 'is_readonly' => !$new])];
    $rows[] = ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title',
        'value_attr' => $vals['title'], 'maxlength_num' => 100, 'is_required' => true])];
    $rows[] = ['label_for' => 'f-intro', 'label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'intro',
        'input_id' => 'f-intro',
        'value_attr' => $vals['intro'], 'maxlength_num' => 1000])];
    $rows[] = ['label_for' => 'f-ext', 'label_html' => _NODE_EXT, 'hint_html' => $esc(_NODE_EXTHINT), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text',
        'name_attr' => 'ext',
        'input_id' => 'f-ext', 'value_attr' => $vals['ext'], 'maxlength_num' => 20])];
    $rows[] = ['label_for' => 'f-sort', 'label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'sort', 'input_id' => 'f-sort',
        'value_attr' => (string)$vals['sort']])];
    $rows[] = $head(_NODE_SECLIST);
    $orders = ['published' => _NEW, 'updated' => _NODE_UPDATED, 'title' => _TITLE, 'views' => _POP, 'rating' => _BEST];
    $rows[] = ['label_html' => _NODE_ORDERS, 'field_html' => implode(' ', array_map(fn(string $k, string $v): string => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'orders[]',
        'value_attr' => $k, 'is_checked' => in_array($k, $set['list']['orders'], true), 'label_text' => $v]), array_keys($orders), $orders))];
    $rows[] = ['label_html' => _NODE_ORDER, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'order', 'options_html' => $opts($orders, $set['list']['order']),
        'is_inline_gap' => true])
        .$tpl->getHtmlFrag('select', ['name_attr' => 'dir', 'options_html' => $opts(['desc' => _DESC, 'asc' => _ASC], $set['list']['dir'])])];
    $rows[] = ['label_for' => 'f-limit', 'label_html' => _NODE_LIMIT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'limit',
        'input_id' => 'f-limit',
        'value_attr' => (string)$set['list']['limit']])];
    $rows[] = ['label_html' => _NODE_ALPHA, 'field_html' => $box('alpha', $set['list']['alpha'], _YES)];
    $show = ['category' => _CATEGORY, 'author' => _POSTEDBY, 'date' => _DATE, 'views' => _READS];
    $rows[] = ['label_html' => _NODE_SHOW, 'field_html' => implode(' ', array_map(fn(string $k, string $v): string => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'show[]',
        'value_attr' => $k, 'is_checked' => in_array($k, $set['list']['show'], true), 'label_text' => $v]), array_keys($show), $show))];
    $rows[] = ['label_for' => 'f-mode', 'label_html' => _NODE_MODE, 'hint_html' => $esc(_NODE_MODEHINT), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text',
        'name_attr' => 'mode',
        'input_id' => 'f-mode', 'value_attr' => $set['view']['mode'], 'maxlength_num' => 20])];
    $rows[] = $head(_NODE_FEATURES);
    $feats = ['categories' => _CATEGORIES, 'comments' => _COMMENTS, 'rating' => _RATING, 'favorites' => _FAVORITES, 'poll' => _VOTING, 'home' => _NODE_FHOME,
        'pinned' => _NODE_FPIN,
        'submit' => _NODE_FSUBMIT, 'moderation' => _NODE_FMOD, 'schedule' => _NODE_FSCHED, 'related' => _NODE_RELATED, 'tree' => _NODE_FTREE];
    $rows[] = ['label_html' => _NODE_FEATURES, 'field_html' => getTplLines(array_map(fn(string $k, string $v): string => $box('feat_'.$k, $set['features'][$k], $v),
        array_keys($feats), $feats), false, true)];
    $rows[] = $head(_NODE_FLOW);
    $flow = $set['workflow'];
    $cur = ($flow['access'] === 'all') ? '0|0' : (($flow['access'] === 'user') ? '1|0' : '2|'.implode(',', $flow['groups']));
    $rows[] = ['label_html' => _NODE_ACCESS, 'field_html' => catacess('access', '', $cur, 0)];
    $rows[] = ['label_html' => _NODE_PUBLISH, 'field_html' => catacess('publish', '', '2|'.implode(',', $flow['publish']), 2)];
    $rows[] = ['label_html' => _NODE_NOTIFY, 'field_html' => getTplLines([$box('notify_pending', $flow['notify']['pending'], _NODE_NOTIFYP),
        $box('notify_result', $flow['notify']['result'], _NODE_NOTIFYR)], false, true)];
    $rows[] = ['label_html' => _NODE_GUESTOK, 'field_html' => $box('guestok', false, _YES)];
    $rows[] = $head(_NODE_ROLES);
    $group = [];
    $list = $set['assets'];
    for ($i = 0; $i < 3; $i++) $list['#'.$i] = null;
    $idx = 0;
    foreach ($list as $key => $def) {
        $def = $def ?? NodeQuery::ROLEDEF;
        $name = str_starts_with((string)$key, '#') ? '' : (string)$key;
        $pre = 'role['.$idx.']';
        $kinds = implode(' ', array_map(fn(string $k): string => $tpl->getHtmlFrag('checkbox', ['name_attr' => $pre.'[kinds][]', 'value_attr' => $k, 'is_checked' => in_array($k,
            (array)$def['kinds'], true),
            'label_text' => $k]), NodeQuery::KINDS));
        $modes = array_combine(array_keys(NodeQuery::RMODES), array_keys(NodeQuery::RMODES));
        $cell = $tpl->getHtmlPart('div', ['rows' => [
            ['label_html' => _NODE_ROLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $pre.'[name]', 'value_attr' => $name,
                'maxlength_num' => 50])],
            ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $pre.'[title]', 'value_attr' => (string)($def['title'] ?? ''),
                'maxlength_num' => 255])],
            ['label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $pre.'[intro]', 'value_attr' => (string)$def['intro'],
                'maxlength_num' => 1000])],
            ['label_html' => _NODE_RMODE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => $pre.'[mode]', 'options_html' => $opts($modes,
                (string)($def['mode'] ?? 'download'))])],
            ['label_html' => _NODE_KINDS, 'field_html' => $kinds],
            ['label_html' => _NODE_EXTS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => $pre.'[extensions]', 'value_attr' => implode(',',
                (array)$def['extensions'])])],
            ['label_html' => _NODE_MAXBYTES, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $pre.'[maxbytes]',
                'value_attr' => ($def['maxbytes'] === null) ? '' : (string)$def['maxbytes']])],
            ['label_html' => _NODE_MIN.' / '._NODE_MAX, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $pre.'[min]',
                'value_attr' => (string)$def['min'],
                'is_inline_gap' => true]).$tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $pre.'[max]', 'value_attr' => (string)($def['max'] ?? 1)])],
            ['label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $pre.'[sort]', 'value_attr' => (string)$def['sort']])],
            ['label_html' => _NODE_ROLEOPTS, 'field_html' => $box($pre.'[canlink]', $def['canlink'], _NODE_CANLINK).' '.$box($pre.'[report]', $def['report'], _NODE_CANREP)
                .' '.$box($pre.'[active]', $def['active'], _NODE_ACTIVE)],
        ]]);
        $group[] = ['is_empty' => $name === '', 'content_html' => $cell];
        $idx++;
    }
    $rows[] = ['label_html' => _NODE_ROLES, 'field_html' => $tpl->getHtmlFrag('repeat', ['rows' => $group, 'add_label' => _ADD]), 'is_full' => true];
    $rows[] = $head(_NODE_INTEG);
    $integ = ['search' => _SEARCH, 'rss' => _RSS, 'sitemap' => _SITEMAP, 'blocks' => _BLOCKS];
    $rows[] = ['label_html' => _NODE_INTEG, 'field_html' => implode(' ', array_map(fn(string $k, string $v): string => $box('integ_'.$k, $set['integrations'][$k], $v),
        array_keys($integ), $integ))];
    $rows[] = ['label_html' => _NODE_SEO, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'seo', 'options_html' => $opts(['website' => 'website',
        'article' => 'article',
        'news' => 'news'], $set['integrations']['seo'])])];
    return $rows;
}

function type(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    checkNodeTypes('type');
    $name = (string)getVar('req', 'type', 'var', '');
    $old = ($name !== '') ? (getNodeTypeMap()[$name] ?? setNodeAdminFault(404, _NODE_GONE, 'types')) : null;
    $pname = (string)getVar('req', 'profile', 'var', '');
    $prof = ($pname !== '') ? (getNodeProfile($pname) ?? setNodeAdminFault(404, _NODE_GONE, 'type')) : null;
    $vals = getNodeTypeVals($old, $prof);
    $ver = $old?->version ?? 0;
    $note = '';
    $clash = false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'type');
        [$input, $vals, $bad] = getNodeTypePost($old, $prof);
        if ($old !== null) $vals['name'] = $old->name;
        $sent = getVar('post', 'version', 'num', 0);
        $act = (string)getVar('post', 'action', 'var', 'save');
        if ($act === 'keep' && $old !== null) {
            $ver = $old->version;
        } elseif ($bad) {
            $ver = $sent;
            $note = implode(' ', $bad);
            http_response_code(422);
        } else {
            try {
                if ($old !== null) {
                    getNodeWriter()->updateNodeType($old->name, $input, $sent);
                    setRedirect($afile.'.php?name=node&op=type&type='.$old->name, false, 302, _NODE_TSAVED);
                }
                if ($prof !== null) {
                    $prof['type'] = array_replace($prof['type'], ['name' => $vals['name'], 'title' => $input->title, 'intro' => $input->intro, 'sort' => $input->sort,
                        'settings' => $input->settings]);
                    getNodeWriter()->addNodeTypeImport(json_encode($prof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $vals['name']);
                } else {
                    getNodeWriter()->addNodeType($vals['name'], $input);
                }
                setRedirect($afile.'.php?name=node&op=types', false, 302, _NODE_TCREATED);
            } catch (NodeException $err) {
                $ver = $sent;
                $note = getNodeTypeFault($err, $vals['name']);
                http_response_code(getNodeStatus($err));
                if ($err->getCode() === NodeException::CONFLICT && $old !== null) {
                    $list = getNodeTypeDiff($old, $vals);
                    $note .= ' '.sprintf(_NODE_DIFF, $list ? implode(', ', $list) : '-');
                    $clash = true;
                }
            } catch (JsonException) {
                $note = sprintf(_NODE_BAD, $vals['name']);
                http_response_code(422);
            }
        }
    }
    setHead();
    $hidden = [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'type'], ['name_attr' => 'token', 'value_attr' => getSiteToken('node')]];
    if ($old !== null) {
        $hidden[] = ['name_attr' => 'type', 'value_attr' => $old->name];
        $hidden[] = ['name_attr' => 'version', 'value_attr' => (string)$ver];
    }
    if ($prof !== null) $hidden[] = ['name_attr' => 'profile', 'value_attr' => $pname];
    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'keep', 'label_text' => _NODE_KEEP, 'is_selected' => true])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SAVECHANGES]);
    $act = $clash ? $tpl->getHtmlFrag('select', ['name_attr' => 'action', 'options_html' => $opts, 'is_inline_gap' => true]) : '';
    $rows = getNodeTypeRows($vals, $old === null);
    if ($old === null) {
        $picks = [];
        foreach (glob(BASE_DIR.'/modules/node/profiles/*.json') ?: [] as $file) {
            $one = basename($file, '.json');
            $label = getConst((string)(getNodeProfile($one)['type']['title'] ?? ''));
            $picks[] = $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=node&op=type&profile='.$one, 'title' => $label, 'label' => $one,
                'is_label_strong' => $one === $pname]);
        }
        if ($picks) array_unshift($rows, ['label_html' => _NODE_PROFILE, 'field_html' => implode(' ', $picks)]);
    }
    $open = $clash ? $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=node&op=type&type='.$old->name, 'title' => _NODE_CURRENT, 'label' => _NODE_CURRENT,
        'is_line_break' => true]) : '';
    echo getNodeAdminTabs($old !== null ? 'types' : 'type')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .$open
        .$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => $hidden,
            'rows' => $rows,
            'actions_html' => $act.$tpl->getHtmlFrag('button', ['submit_label' => $clash ? _OK : _SAVECHANGES, 'button_type' => 'submit']),
        ])]);
    setFoot();
}

function typestatus(): void {
    global $afile;
    checkNodeMethod(['POST']);
    checkNodeTypes('types');
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'types');
    $name = (string)getVar('post', 'type', 'var', '');
    $on = (string)getVar('post', 'active', 'raw', '');
    if (!in_array($on, ['0', '1'], true)) setNodeAdminFault(422, _NODE_INVALID, 'types');
    try {
        getNodeWriter()->updateNodeTypeStatus($name, $on === '1', getVar('post', 'version', 'num', 0));
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeTypeFault($err, $name), 'types');
    }
    setRedirect($afile.'.php?name=node&op=types', false, 302, ($on === '1') ? _NODE_TYPEON : _NODE_TYPEOFF);
}

function typedelete(): void {
    global $afile;
    checkNodeMethod(['POST']);
    checkNodeTypes('types');
    if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'types');
    $name = (string)getVar('post', 'type', 'var', '');
    try {
        getNodeWriter()->deleteNodeType($name, getVar('post', 'version', 'num', 0));
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeTypeFault($err, $name), 'types');
    }
    setRedirect($afile.'.php?name=node&op=types', false, 302, _NODE_TDELETED);
}

function typeclone(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    checkNodeTypes('types');
    $name = (string)getVar('req', 'type', 'var', '');
    $old = getNodeTypeMap()[$name] ?? setNodeAdminFault(404, _NODE_GONE, 'types');
    $note = '';
    $new = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'types');
        $new = trim((string)getVar('post', 'tname', 'raw', ''));
        try {
            $json = getNodeReader()->getNodeTypeExport($old->name);
            getNodeWriter()->addNodeTypeImport($json, $new);
            setRedirect($afile.'.php?name=node&op=types', false, 302, _NODE_TCREATED);
        } catch (NodeException $err) {
            $note = getNodeTypeFault($err, $new);
            http_response_code(getNodeStatus($err));
        }
    }
    setHead();
    echo getNodeAdminTabs('types')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .$tpl->getHtmlPart('box', ['title' => _NODE_CLONE.': '.$old->name, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'clone'], ['name_attr' => 'type', 'value_attr' => $old->name],
                ['name_attr' => 'token', 'value_attr' => getSiteToken('node')]],
            'rows' => [['label_for' => 'f-tname', 'label_html' => _NODE_NEWNAME, 'hint_html' => htmlspecialchars(_NODE_NAMEHINT, ENT_QUOTES, 'UTF-8'),
                'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'tname', 'input_id' => 'f-tname', 'value_attr' => $new, 'maxlength_num' => 20,
                'is_required' => true])]],
            'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _NODE_CLONE, 'button_type' => 'submit']),
        ])]);
    setFoot();
}

function export(): void {
    checkNodeMethod(['GET', 'HEAD']);
    checkNodeTypes('types');
    $name = (string)getVar('get', 'type', 'var', '');
    try {
        $json = getNodeReader()->getNodeTypeExport($name);
    } catch (NodeException $err) {
        setNodeAdminFault(getNodeStatus($err), getNodeTypeFault($err, $name), 'types');
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="node-'.$name.'.json"');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit;
}

function import(): void {
    global $afile, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    checkNodeTypes('import');
    $note = '';
    $new = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'import');
        $new = trim((string)getVar('post', 'tname', 'raw', ''));
        $file = $_FILES['file'] ?? null;
        $json = '';
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)$file['tmp_name']) && (int)$file['size'] <= 1048576) {
            $json = (string)file_get_contents((string)$file['tmp_name']);
        }
        try {
            getNodeWriter()->addNodeTypeImport($json, $new);
            setRedirect($afile.'.php?name=node&op=types', false, 302, _NODE_TCREATED);
        } catch (NodeException $err) {
            $note = getNodeTypeFault($err, $new);
            http_response_code(getNodeStatus($err));
        }
    }
    setHead();
    echo getNodeAdminTabs('import')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .$tpl->getHtmlPart('box', ['title' => _NODE_IMPORT, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'form_attr' => 'enctype="multipart/form-data"',
            'hidden' => [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'import'], ['name_attr' => 'token',
                'value_attr' => getSiteToken('node')]],
            'rows' => [
                ['label_for' => 'f-file', 'label_html' => _NODE_JSON, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'file', 'name_attr' => 'file', 'input_id' => 'f-file',
                    'input_attr' => 'accept="application/json,.json"', 'is_required' => true])],
                ['label_for' => 'f-tname', 'label_html' => _NODE_NEWNAME, 'hint_html' => htmlspecialchars(_NODE_NAMEHINT, ENT_QUOTES, 'UTF-8'),
                    'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'tname', 'input_id' => 'f-tname', 'value_attr' => $new, 'maxlength_num' => 20])],
            ],
            'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _NODE_IMPORT, 'button_type' => 'submit']),
        ])]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    checkNodeTypes('config');
    $keys = ['maxassets' => _NODE_MAXASSETS, 'maxlist' => _NODE_MAXLIST, 'syncbatch' => _NODE_SYNCBATCH, 'send' => _NODE_SEND];
    $lims = $conf['node']['limits'] ?? [];
    $note = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, 'config');
        $new = [];
        foreach (array_keys($keys) as $key) {
            $raw = trim((string)getVar('post', $key, 'raw', ''));
            $new[$key] = preg_match(($key === 'send') ? '/^(?:0|[1-9][0-9]{0,8})$/D' : '/^[1-9][0-9]{0,8}$/D', $raw) ? (int)$raw : -1;
        }
        $lims = $new;
        $held = '';
        $code = 422;
        if (in_array(-1, $new, true)) {
            $note = _NODE_INVALID;
        } else {
            $types = getNodeTypeMap();
            $done = setConfigFile(function (array $base, Closure $save) use ($new, $types, &$held): string {
                global $conf;
                if (!is_array($base['node'] ?? null)) return 'aborted';
                $keep = $conf['node'] ?? null;
                $conf['node'] = ['limits' => $new] + $base['node'];
                $query = getNodeReader();
                $list = ['-' => ['', getNodeTypeBase(), []]];
                foreach ((array)($base['node']['types'] ?? []) as $name => $sect) {
                    if (!isset($types[$name]) || !is_array($sect)) continue;
                    unset($sect['version']);
                    $list[$name] = [$types[$name]->ext, $sect, $types[$name]->fields];
                }
                foreach ($list as $name => [$ext, $sect, $defs]) {
                    try {
                        $query->filterNodeSettings($ext, $sect, $defs);
                    } catch (NodeException) {
                        $held = (string)$name;
                        break;
                    }
                }
                $conf['node'] = $keep;
                if ($held !== '') return 'aborted';
                $base['node']['limits'] = $new;
                return $save($base) ? 'committed' : 'aborted';
            });
            if ($done && $held === '') setRedirect($afile.'.php?name=node&op=config', false, 302, _NODE_SAVED);
            $jour = ($held === '') ? getConfigJournal() : [];
            $note = ($held !== '') ? sprintf(_NODE_LIMITNO, $held) : ($jour ? _CONFIG_PENDING : _ERROR_UP);
            $code = ($held !== '') ? 422 : ($jour ? 409 : 500);
        }
        http_response_code($code);
    }
    setHead();
    $rows = [['label_html' => _NODE_FORMAT, 'field_html' => htmlspecialchars((string)($conf['node']['version'] ?? ''), ENT_QUOTES, 'UTF-8')]];
    foreach ($keys as $key => $label) {
        $rows[] = ['label_for' => 'f-'.$key, 'label_html' => $label, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => $key, 'input_id' => 'f-'.$key,
            'value_attr' => (string)($lims[$key] ?? ''), 'is_required' => true])];
    }
    echo getNodeAdminTabs('config')
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8')]) : '')
        .$tpl->getHtmlPart('box', ['title' => _NODE_LIMITS, 'content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'config'], ['name_attr' => 'token',
                'value_attr' => getSiteToken('node')]],
            'rows' => $rows,
            'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _SAVECHANGES, 'button_type' => 'submit']),
        ])]);
    setFoot();
}

function support(): void {
    global $afile, $tpl, $prs, $com;
    checkNodeMethod(['GET', 'HEAD', 'POST']);
    [$type, $node] = getNodeAdminItem(getVar('req', 'id', 'num', 0), '');
    $hand = getNodeHandler($type);
    if (!$hand instanceof NodeSupport) setNodeAdminFault(404, _NODE_GONE);
    $self = $afile.'.php?name=node&op=support&id='.$node->id;
    $card = $hand->getNodeData($type, [$node], 'admin')[$node->id] ?? setNodeAdminFault(404, _NODE_GONE);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!checkAdminPost('node')) setNodeAdminFault(403, _TOKENMISS, '', $self);
        $num = fn(string $key): int => getVar('post', $key, 'num', 0);
        try {
            $hand->updateNodeSupport($node->id, $num('aid'), $num('state'), $num('prio'), $num('version'));
        } catch (NodeException $err) {
            setNodeAdminFault(getNodeStatus($err), ($err->getCode() === NodeException::CONFLICT) ? _NODE_AGAIN : getNodeFault($err), '', $self);
        }
        setRedirect($self, false, 302, _NODE_SSAVED);
    }
    $labs = getNodeSupportLabels();
    $view = getNodeViewData($type, $node, 'view');
    setHead();
    $who = ($view['author'] !== '') ? $view['author'] : _ANONYM;
    $text = $tpl->getHtmlFrag('link', ['href' => $view['href'], 'title' => _MVIEW, 'label' => $node->title, 'is_line_break' => true])
        .$tpl->getHtmlFrag('span', ['text' => ' '.$who.' - '.$view['date'], 'is_line_break' => true]).$view['intro_html'].$view['body_html'];
    $talk = '';
    foreach ($com->getList($type->name, $node->id, 1)['rows'] as $one) {
        $name = (string)($one['user']['name'] ?? '') ?: ($one['name'] ?: _ANONYM);
        $talk .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_author' => true, 'has_content_text' => true, 'content_text' => $name],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time($one['time'], _TIMESTRING)],
            ['content_html' => ($one['deleted'] !== '') ? '' : $prs->filterContent($one['body'], true, $type->name, 2, 'breaks')],
        ]])]);
    }
    $rows = [
        ['label_for' => 'f-aid', 'label_html' => _NODE_ASSIGN, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'aid', 'selectid' => 'f-aid',
            'options_html' => getNodeSupportOpts([0 => _NODE_NOBODY] + getAdminNames('node-'.$type->name), $card['aid'])])],
        ['label_for' => 'f-state', 'label_html' => _NODE_STATE, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'state', 'selectid' => 'f-state',
            'options_html' => getNodeSupportOpts($labs['state'], $card['state'])])],
        ['label_for' => 'f-prio', 'label_html' => _NODE_PRIO, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'prio', 'selectid' => 'f-prio',
            'options_html' => getNodeSupportOpts($labs['prio'], $card['prio'])])],
        ['label_html' => _NODE_LAST, 'field_html' => htmlspecialchars(format_time($card['activity'], _TIMESTRING), ENT_QUOTES, 'UTF-8')],
    ];
    $hidden = [['name_attr' => 'name', 'value_attr' => 'node'], ['name_attr' => 'op', 'value_attr' => 'support'], ['name_attr' => 'id', 'value_attr' => (string)$node->id],
        ['name_attr' => 'version', 'value_attr' => (string)$card['version']], ['name_attr' => 'token', 'value_attr' => getSiteToken('node')]];
    echo getNodeAdminTabs('')
        .$tpl->getHtmlPart('box', ['title' => _NODE_CARD, 'content_html' => $tpl->getHtmlPart('form', ['action_url' => $afile.'.php', 'hidden' => $hidden, 'rows' => $rows,
            'actions_html' => $tpl->getHtmlFrag('button', ['submit_label' => _SAVECHANGES, 'button_type' => 'submit'])])])
        .$tpl->getHtmlPart('box', ['title' => getModuleName($type->name), 'content_html' => $text])
        .$tpl->getHtmlPart('box', ['title' => _COMMENTS, 'content_html' => ($talk === '') ? $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NOCOMMENTS])
            : $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'head' => [['content' => _POSTEDBY, 'is_col_author' => true], ['content' => _DATE, 'is_col_date' => true],
                ['content' => _COMMENT]], 'rows_html' => $talk])]);
    setFoot();
}

function info(): void {
    checkNodeMethod(['GET', 'HEAD']);
    $ops = getNodeAdminOps();
    setTplAdminInfoPage(['ops' => array_column($ops, 1), 'tabs' => array_column($ops, 0)]);
}

switch ($op) {
    case 'show': show(); break;
    case 'add': add(); break;
    case 'edit': edit(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'report': report(); break;
    case 'types': types(); break;
    case 'type': type(); break;
    case 'typestatus': typestatus(); break;
    case 'typedelete': typedelete(); break;
    case 'remains': remains(); break;
    case 'clone': typeclone(); break;
    case 'export': export(); break;
    case 'import': import(); break;
    case 'config': config(); break;
    case 'support': support(); break;
    case 'sync': sync(); break;
    case 'info': info(); break;
    default: setNodeAdminFault(404, _NODE_GONE);
}
