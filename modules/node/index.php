<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) {
    header('Location: ../../index.php');
    exit;
}

# The one public entry point of every registered Node type: the list, the material, the public form, the resource, the attachment and the report of a resource
# The routes of this file take their type from the loaded registry and their rights from the Node context; they run no SQL of their own and build no HTML beyond the templates
# The helpers above the routing are shared with the administration of Node, which includes this file for them and leaves the public routing below untouched

# The closed public operations of a type and the methods each one answers; the extension support adds its one operation, no other extension adds any
function getNodeOps(string $ext = ''): array {
    $ops = ['' => ['GET', 'HEAD'], 'view' => ['GET', 'HEAD'], 'add' => ['GET', 'HEAD', 'POST'], 'asset' => ['GET', 'HEAD'], 'attach' => ['GET', 'HEAD'], 'report' => ['POST']];
    return ($ext === 'support') ? $ops + ['support' => ['POST']] : $ops;
}

# A reader of the request context, bound to the extension of the type when one type is read
function getNodeReader(?NodeType $type = null): NodeQuery {
    global $db, $fld;
    $query = new NodeQuery($db, getNodeContext(), $fld);
    return ($type !== null) ? $query->setNodeExtension(getNodeHandler($type)) : $query;
}

# The writer of the request context with the shared points, bound to the extension of the type a material write serves
function getNodeWriter(?NodeType $type = null): NodeService {
    global $db, $fld, $pnt;
    return new NodeService($db, getNodeContext(), $fld, $pnt, ($type !== null) ? getNodeHandler($type) : null);
}

# The template of one view part of the type: the file of its display mode when the theme carries one, otherwise the base file of Node, answered once per request
function getNodeTplName(string $kind, string $name, NodeType $type): string {
    global $tpl;
    static $memo = [];
    $mode = $type->settings['view']['mode'];
    $key = $kind.'|'.$mode.'|'.$name;
    if (!isset($memo[$key])) $memo[$key] = ($mode !== 'default' && $tpl->checkTemplateFile($kind, 'node/'.$mode.'/'.$name)) ? 'node/'.$mode.'/'.$name : 'node/'.$name;
    return $memo[$key];
}

# A plain label of the type configuration: a language constant is resolved, plain text stays as it is
function getNodeLabel(string $text): string {
    return ($text !== '' && $text[0] === '_' && defined($text)) ? (string)constant($text) : $text;
}

# The safe text of a refusal by its code; the message of the exception never reaches the page, only the label of a refused source field of an external material,
# which only the administrative form sends, so the two administrative labels are read there alone
function getNodeFault(NodeException $err): string {
    $text = match ($err->getCode()) {
        NodeException::NOTFOUND => _NODE_GONE,
        NodeException::DENIED => _ACCESSDENIED,
        NodeException::INVALID => _NODE_INVALID,
        NodeException::CONFLICT => _NODE_BUSY,
        default => _NODE_FAILED,
    };
    if ($err->getCode() === NodeException::INVALID && preg_match('/: ext\.(url|refresh)$/D', $err->getMessage(), $hit)) {
        $text .= ' ('.(($hit[1] === 'url') ? _NODE_SOURCE : _NODE_PERIOD).')';
    }
    return $text;
}

# The HTTP status of a refusal by its code
function getNodeStatus(NodeException $err): int {
    return [NodeException::NOTFOUND => 404, NodeException::DENIED => 403, NodeException::INVALID => 422, NodeException::CONFLICT => 409][$err->getCode()] ?? 500;
}

# The field codes of a refused field value, when the refusal names one; the service reports the first error by the path fields.<name>.<code>
function getNodeFieldErrors(NodeException $err): array {
    return preg_match('/: fields\.([a-z][a-z0-9_]{0,31})\.([a-z]+)$/D', $err->getMessage(), $hit) ? [$hit[1] => $hit[2]] : [];
}

# One date of a form as a canonical database date, null when it was left empty and false when it is no date
function getNodeFormDate(string $text): string|false|null {
    $text = trim(str_replace('T', ' ', $text));
    if ($text === '') return null;
    $time = strtotime($text);
    return ($time !== false && date('Y-m-d H:i', $time) === substr($text, 0, 16)) ? date('Y-m-d H:i:00', $time) : false;
}

# The kind a file name or an address suggests by its extension, fitted to the kinds the display mode of the role accepts
function getNodeAssetKind(string $src, array $def): string {
    $ext = strtolower(pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
    $kind = match (true) {
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'], true) => 'image',
        in_array($ext, ['mp3', 'wav', 'ogg', 'oga', 'flac', 'm4a', 'aac', 'opus'], true) => 'audio',
        in_array($ext, ['mp4', 'webm', 'ogv', 'mov', 'm4v'], true) => 'video',
        default => 'file',
    };
    $allow = array_values(array_intersect(NodeQuery::RMODES[$def['mode']], $def['kinds'] ?: NodeQuery::KINDS));
    if (in_array($kind, $allow, true)) return $kind;
    return in_array('file', $allow, true) ? 'file' : ($allow[0] ?? 'file');
}

# The rows of the resource form: every stored resource of an active role, then empty rows up to the limit of the role, at most four new ones for each
function getNodeAssetList(NodeType $type, array $list): array {
    $out = [];
    foreach ($type->settings['assets'] as $role => $def) {
        if (!$def['active']) continue;
        $have = array_values(array_filter($list, fn($v) => $v['role'] === $role));
        foreach ($have as $one) $out[] = $one;
        for ($i = count($have); $i < min($def['max'], count($have) + 4); $i++) {
            $out[] = ['id' => null, 'role' => $role, 'kind' => '', 'src' => '', 'name' => '', 'title' => '', 'intro' => ''];
        }
    }
    return $out;
}

# Read the resource rows of a posted form: a new multipart file is stored first through the shared upload service, a picked path or an address is taken as it came
# A row without any source is dropped, which removes a stored resource the form carried; the refusal of an upload is answered as text and nothing is written for it
function getNodeAssetPost(NodeType $type, array $was): array {
    $rule = getUploadPlaceRule($type->name.'.attach');
    $rows = getVar('post', 'asset[]', '', []);
    $keep = [];
    foreach ($was as $one) $keep[$one->id] = $one;
    $list = [];
    $show = [];
    $errs = [];
    foreach (is_array($rows) ? $rows : [] as $idx => $row) {
        if (!is_int($idx) || !is_array($row)) continue;
        $role = is_string($row['role'] ?? null) ? $row['role'] : '';
        $def = $type->settings['assets'][$role] ?? null;
        if ($def === null) continue;
        $aid = (int)($row['id'] ?? 0);
        $src = '';
        $file = $_FILES['afile'.$idx] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $res = getUploadService()->addUploadedFile($file, $rule, (string)$rule['store'], (string)$rule['mod'], getEditorFileOwner($type->name));
            if ($res['ok']) $src = (string)$res['file'];
            else $errs[] = getUploadFailText((string)$res['error'], $rule);
        }
        if ($src === '') $src = trim((string)getVar('post', 'apath'.$idx, 'raw', ''));
        if ($src === '') $src = trim((string)getVar('post', 'aurl'.$idx, 'raw', ''));
        $text = fn(string $key): string => is_string($row[$key] ?? null) ? trim($row[$key]) : '';
        $one = ['id' => $aid ?: null, 'role' => $role, 'kind' => '', 'src' => $src, 'name' => $text('name'), 'title' => $text('title'), 'intro' => $text('intro')];
        $show[] = $one;
        if ($src === '') continue;
        $old = $keep[$aid] ?? null;
        $one['kind'] = ($old !== null && $old->src === $src && $old->role === $role) ? $old->kind : getNodeAssetKind($src, $def);
        $one['sort'] = count($list);
        $list[] = $one;
    }
    return [$list, $show, $errs];
}

# The resources of a stored material as form rows
function getNodeAssetRows(?Node $node): array {
    $out = [];
    foreach ($node?->assets ?? [] as $one) {
        $out[] = ['id' => $one->id, 'role' => $one->role, 'kind' => $one->kind, 'src' => $one->src, 'name' => $one->name, 'title' => $one->title, 'intro' => $one->intro];
    }
    return $out;
}

# Read the posted content of a material into the input the service takes and into the values the form shows again; the state travels separately
# A visitor who does not moderate the type sends no poll, home, pin or date, and the comment mode of a public submission is the open one where the type has comments
# A type with the extension sync takes the address and the period of its source instead of a body: the body stays the one its source last brought
function getNodeFormPost(NodeType $type, bool $moder, ?Node $old): array {
    $feat = $type->settings['features'];
    $sync = $type->ext === 'sync';
    $ext = [];
    $src = [];
    if ($sync) {
        $src = ['url' => trim((string)getVar('post', 'source', 'raw', '')), 'refresh' => trim((string)getVar('post', 'refresh', 'raw', ''))];
        $ext = ['url' => $src['url'], 'refresh' => preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', $src['refresh']) ? (int)$src['refresh'] : -1];
    }
    $cats = array_values(array_unique(array_filter(array_map('intval', (array)getVar('post', 'cids[]', '', [])), fn($v) => $v > 0)));
    $rels = [];
    if ($feat['related']) {
        foreach (preg_split('/[\s,;]+/', (string)getVar('post', 'relrel', 'raw', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $i => $rid) {
            if (ctype_digit($rid) && (int)$rid > 0) $rels[] = ['rid' => (int)$rid, 'type' => 'related', 'sort' => $i];
        }
    }
    $up = (int)getVar('post', 'relparent', 'num', 0);
    if ($feat['tree'] && $up > 0) $rels[] = ['rid' => $up, 'type' => 'parent', 'sort' => 0];
    [$assets, $show, $errs] = getNodeAssetPost($type, $old?->assets ?? []);
    $pub = $moder ? getNodeFormDate((string)getVar('post', 'pubdate', 'raw', '')) : null;
    $end = ($moder && $feat['schedule']) ? getNodeFormDate((string)getVar('post', 'expires', 'raw', '')) : null;
    if ($pub === false || $end === false) $errs[] = _NODE_BADDATE;
    $mode = CommentMode::tryFrom((int)getVar('post', 'comon', 'num', 0)) ?? CommentMode::Disabled;
    if (!$feat['comments']) $mode = CommentMode::Disabled;
    elseif (!$moder) $mode = CommentMode::Open;
    $vals = [
        'cid' => $feat['categories'] ? (int)getVar('post', 'cid', 'num', 0) : 0,
        'cids' => $feat['categories'] ? $cats : [],
        'aname' => (getNodeContext()->uid > 0 || $old !== null) ? ($old?->aname ?? '') : trim((string)getVar('post', 'aname', 'raw', '')),
        'title' => trim((string)getVar('post', 'title', 'raw', '')),
        'intro' => (string)getVar('post', 'intro', 'raw', ''),
        'body' => $sync ? (string)($old?->body ?? '') : (string)getVar('post', 'body', 'raw', ''),
        'fields' => (array)getVar('post', 'field[]', '', []),
        'poll' => ($moder && $feat['poll']) ? (int)getVar('post', 'poll', 'num', 0) : ($old?->poll ?? 0),
        'home' => $moder && $feat['home'] && getVar('post', 'home', 'num', 0) === 1,
        'comon' => $mode,
        'pinned' => $moder && $feat['pinned'] && getVar('post', 'pinned', 'num', 0) === 1,
        'pubdate' => is_string($pub) ? $pub : null,
        'expires' => is_string($end) ? $end : null,
        'rels' => $rels,
        'assets' => $show,
        'ext' => $src,
    ];
    $input = new NodeInput($vals['cid'], $vals['cids'], $vals['aname'], $vals['title'], $vals['intro'], $vals['body'], $vals['fields'], $vals['poll'], $vals['home'],
        $vals['comon'], $vals['pinned'], $vals['pubdate'], $vals['expires'], $rels, $assets, $ext);
    return [$input, $vals, $errs];
}

# The values a form shows for a stored material, or the empty values of a new one; the source of an external material comes from its extension
function getNodeFormVals(?Node $node, array $ext = []): array {
    $rels = $node?->rels ?? [];
    $near = array_values(array_filter($rels, fn($v) => $v->type === 'related'));
    $up = array_values(array_filter($rels, fn($v) => $v->type === 'parent'));
    return [
        'cid' => $node?->cid ?? 0,
        'cids' => $node?->cids ?? [],
        'aname' => $node?->aname ?? '',
        'title' => $node?->title ?? '',
        'intro' => $node?->intro ?? '',
        'body' => (string)($node?->body ?? ''),
        'fields' => $node?->fields ?? [],
        'poll' => $node?->poll ?? 0,
        'home' => $node?->home ?? false,
        'comon' => $node?->comon ?? CommentMode::Disabled,
        'pinned' => $node?->pinned ?? false,
        'pubdate' => $node?->pubdate,
        'expires' => $node?->expires,
        'rels' => array_merge(array_map(fn($v) => ['rid' => $v->rid, 'type' => 'related', 'sort' => $v->sort], $near),
            array_map(fn($v) => ['rid' => $v->rid, 'type' => 'parent', 'sort' => 0], $up)),
        'assets' => getNodeAssetRows($node),
        'ext' => $ext,
    ];
}

# The options of the category selects of the type, from the shared category map with its tree order; the rights of each category are decided by the writer
function getNodeCatOptions(NodeType $type, array $pick, bool $empty): string {
    global $tpl;
    $map = getCategoryMap($type->name);
    $out = $empty ? $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => !$pick]) : '';
    $walk = function (int $up, int $deep) use (&$walk, &$out, $map, $pick, $tpl): void {
        $kids = array_filter($map, fn($v) => $v['parent'] === $up);
        uasort($kids, fn($a, $b) => [$a['ordern'], $a['title']] <=> [$b['ordern'], $b['title']]);
        foreach ($kids as $cid => $one) {
            $label = str_repeat(html_entity_decode('&nbsp;', ENT_QUOTES, 'UTF-8'), $deep * 4).html_entity_decode(getConst($one['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$cid, 'label_text' => $label, 'is_selected' => in_array($cid, $pick, true)]);
            if ($deep < 20) $walk($cid, $deep + 1);
        }
    };
    $walk(0, 0);
    return $out;
}

# The editor of one text of the material: the active editor of the site with the upload place and the storage of the column
function getNodeEditor(NodeType $type, string $key, string $value, string $label): string {
    $base = ['name' => $key, 'value' => $value, 'mod' => $type->name, 'label' => $label, 'placeholder' => $label];
    if ($key === 'intro') return getTplTextarea(['id' => '1', 'store' => 'nodes.intro', 'rows' => 5] + $base);
    return getTplTextarea(['id' => '2', 'store' => 'nodes.body', 'rows' => 12] + $base);
}

# The rows of the resource section: one repeatable group per active role, each row with the shared file field of the upload place of the type
function getNodeAssetHtml(NodeType $type, array $list): string {
    global $tpl;
    $out = '';
    $idx = 0;
    $rows = getNodeAssetList($type, $list);
    foreach ($type->settings['assets'] as $role => $def) {
        if (!$def['active']) continue;
        $group = [];
        foreach ($rows as $one) {
            if ($one['role'] !== $role) continue;
            $fid = 'f-asset-'.$idx;
            $link = str_starts_with($one['src'], 'http://') || str_starts_with($one['src'], 'https://');
            $cell = $tpl->getHtmlFrag('hidden', ['name_attr' => 'asset['.$idx.'][role]', 'value_attr' => $role, 'input_attr' => ''])
                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'asset['.$idx.'][id]', 'value_attr' => (string)($one['id'] ?? ''), 'input_attr' => ''])
                .getFileManagerField(['id' => $fid, 'place' => $type->name.'.attach', 'name' => 'afile'.$idx, 'path' => 'apath'.$idx, 'url' => 'aurl'.$idx,
                    'path_value' => $link ? '' : $one['src'], 'url_value' => $link ? $one['src'] : ''])
                .$tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'asset['.$idx.'][title]', 'value_attr' => $one['title'], 'maxlength_num' => 100,
                    'placeholder_text' => _TITLE]);
            $group[] = ['is_empty' => $one['src'] === '', 'content_html' => $cell];
            $idx++;
        }
        $hint = getNodeLabel($def['intro']);
        $out .= $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => getNodeLabel($def['title']).($hint !== '' ? ' - '.$hint : '')])
            .$tpl->getHtmlFrag('repeat', ['rows' => $group, 'add_label' => _ADD]);
    }
    return $out;
}

# The shared rows of a material form as neutral data - label, target, hint and control - so the public form and the administration lay them out in their own templates
# Only the controls the type and the right of the visitor allow are built; the writer refuses every value the form did not offer anyway
function getNodeFormRows(NodeType $type, array $vals, array $errs, bool $moder, bool $admin): array {
    global $tpl, $fld;
    $feat = $type->settings['features'];
    $rows = [];
    $rows[] = ['label' => _TITLE, 'for' => 'f-title', 'field' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title',
        'value_attr' => $vals['title'], 'maxlength_num' => 100, 'is_required' => true])];
    if (getNodeContext()->uid < 1 && !$admin) {
        $rows[] = ['label' => _YOURNAME, 'for' => 'f-aname', 'field' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'aname', 'input_id' => 'f-aname',
            'value_attr' => $vals['aname'], 'maxlength_num' => 25])];
    }
    if ($feat['categories']) {
        $rows[] = ['label' => _CATEGORY, 'for' => 'f-cid', 'field' => $tpl->getHtmlFrag('select', ['name_attr' => 'cid', 'input_id' => 'f-cid', 'selectid' => 'f-cid',
            'options_html' => getNodeCatOptions($type, [$vals['cid']], true)])];
        $rows[] = ['label' => _NODE_CATS, 'for' => 'f-cids', 'field' => $tpl->getHtmlFrag('select', ['name_attr' => 'cids', 'selectid' => 'f-cids', 'is_multiple' => true,
            'is_name_array' => true, 'options_html' => getNodeCatOptions($type, $vals['cids'], false)])];
    }
    $rows[] = ['label' => _NODE_INTRO, 'field' => getNodeEditor($type, 'intro', $vals['intro'], _NODE_INTRO), 'full' => true];
    if ($type->ext !== 'sync') {
        $rows[] = ['label' => _TEXT, 'field' => getNodeEditor($type, 'body', $vals['body'], _TEXT), 'full' => true];
    } elseif ($admin) {
        $rows[] = ['label' => _NODE_SOURCE, 'for' => 'f-source', 'field' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'source', 'input_id' => 'f-source',
            'value_attr' => (string)($vals['ext']['url'] ?? ''), 'maxlength_num' => 2048, 'placeholder_text' => 'https://example.com/feed.xml', 'is_required' => true])];
        $rows[] = ['label' => _NODE_PERIOD, 'for' => 'f-refresh', 'hint' => _NODE_PERHINT, 'hint_id' => 'f-refresh-hint', 'field' => $tpl->getHtmlFrag('input', [
            'describedby' => 'f-refresh-hint', 'itype' => 'number', 'name_attr' => 'refresh', 'input_id' => 'f-refresh', 'value_attr' => (string)($vals['ext']['refresh'] ?? 3600),
            'is_required' => true, 'input_attr' => 'min="0" max="31536000"'])];
    }
    foreach ($fld->getFieldForm($tpl, $type->fields, $vals['fields'], $errs) as $one) {
        $rows[] = ['label' => $one['label_text'], 'for' => $one['label_for'], 'hint' => trim($one['hint_text'].' '.$one['error_text']), 'hint_id' => $one['hint_id'],
            'field' => $one['field_html']];
    }
    if ($feat['related']) {
        $near = implode(', ', array_column(array_filter($vals['rels'], fn($v) => $v['type'] === 'related'), 'rid'));
        $rows[] = ['label' => _NODE_RELATED, 'for' => 'f-relrel', 'hint' => _NODE_RELHINT, 'field' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'relrel',
            'input_id' => 'f-relrel', 'value_attr' => $near, 'maxlength_num' => 4000])];
    }
    if ($feat['tree']) {
        $up = array_values(array_filter($vals['rels'], fn($v) => $v['type'] === 'parent'))[0]['rid'] ?? '';
        $rows[] = ['label' => _NODE_PARENT, 'for' => 'f-relparent', 'field' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'relparent',
            'input_id' => 'f-relparent', 'value_attr' => (string)$up])];
    }
    if ($type->settings['assets'] && array_filter($type->settings['assets'], fn($v) => $v['active'])) {
        $rows[] = ['label' => _NODE_ASSETS, 'field' => getNodeAssetHtml($type, $vals['assets']), 'full' => true];
    }
    return $rows;
}

# One material or light target of the type prepared by the shared view preparer; the unsaved material of a preview goes the same way
function getNodeViewData(NodeType $type, Node|NodeTarget $node, string $mode): array {
    global $prs, $fld;
    return (new NodeView($prs, $fld))->getNodeView($type, $node, $mode);
}

# The labels of the states and the priorities of a request of support, keyed by the numbers the two maps of config/node.php store
function getNodeSupportLabels(): array {
    global $conf;
    $slab = ['staff' => _NODE_WSTAFF, 'author' => _NODE_WUSER, 'closed' => _NODE_WCLOSE];
    $plab = ['low' => _NODE_PLOW, 'normal' => _NODE_PNORM, 'high' => _NODE_PHIGH, 'urgent' => _NODE_PURGE];
    $out = ['state' => [], 'prio' => []];
    foreach ((array)($conf['node']['support']['state'] ?? []) as $key => $val) $out['state'][$val] = $slab[$key] ?? (string)$key;
    foreach ((array)($conf['node']['support']['prio'] ?? []) as $key => $val) $out['prio'][$val] = $plab[$key] ?? (string)$key;
    return $out;
}

# The data the extension of a type prepared for one material, with the labels and the owner switch its templates show for a request of support
# The request of support shows its state and priority by the names of the two maps of config/node.php; only its owner gets the switch that closes or reopens it
function getNodeExtVars(NodeType $type, Node|NodeTarget $node, array $data): array {
    global $conf, $afile;
    if ($type->ext !== 'support' || $data === []) return $data;
    $maps = $conf['node']['support'];
    $labs = getNodeSupportLabels();
    $skey = (string)array_search($data['state'], $maps['state'], true);
    $shut = $skey === 'closed';
    $owner = getNodeContext()->uid > 0 && getNodeContext()->uid === $node->uid;
    return $data + [
        'state_label' => $labs['state'][$data['state']] ?? '',
        'prio_label' => $labs['prio'][$data['prio']] ?? '',
        'is_closed' => $shut,
        'is_staff' => $skey === 'staff',
        'is_author' => $skey === 'author',
        'state_title' => _NODE_STATE,
        'prio_title' => _NODE_PRIO,
        'is_owner' => $owner,
        'action' => $owner ? getSeoUrl(['name' => $type->name, 'op' => 'support', 'id' => $node->id]) : '',
        'token' => $owner ? getSiteToken() : '',
        'next' => $shut ? $maps['state']['staff'] : $maps['state']['closed'],
        'switch_label' => $shut ? _NODE_REOPEN : _NODE_CLOSE,
        'card_href' => checkNodeModer($type) ? $afile.'.php?name=node&op=support&id='.$node->id : '',
    ];
}

# The labels and switches the standard templates of a material read beside the prepared data
function getNodeViewVars(NodeType $type): array {
    $show = $type->settings['list']['show'];
    return [
        'is_date' => in_array('date', $show, true),
        'is_author' => in_array('author', $show, true),
        'is_category' => in_array('category', $show, true),
        'is_views' => in_array('views', $show, true),
        'date_label' => _DATE,
        'views_label' => _READS,
        'author_label' => _POSTEDBY,
        'read_label' => _READMORE,
    ];
}

# The first image of a prepared material among the roles shown as an image or a gallery, used as the cover of its card
function getNodeCover(NodeType $type, array $view): string {
    foreach ($type->settings['assets'] as $role => $def) {
        if (!in_array($def['mode'], ['image', 'gallery'], true)) continue;
        foreach ($view['assets'][$role] ?? [] as $one) if ($one['href'] !== '' && $one['kind'] === 'image') return $one['href'];
    }
    return '';
}

# Render the resources of a prepared material, one fragment of the display mode of each role; a role shown by no mode of its own stays out, the poster goes to the player
function getNodeAssetView(NodeType $type, array $view): string {
    global $tpl;
    $out = '';
    $poster = '';
    foreach ($type->settings['assets'] as $role => $def) {
        if ($def['mode'] === 'none' && $def['kinds'] === ['image']) $poster = $poster ?: (string)($view['assets'][$role][0]['href'] ?? '');
    }
    foreach ($type->settings['assets'] as $role => $def) {
        $items = $view['assets'][$role] ?? [];
        if (!$items || $def['mode'] === 'none') continue;
        foreach ($items as $i => $one) $items[$i] += ['is_video' => $one['kind'] === 'video', 'is_audio' => $one['kind'] === 'audio'];
        $out .= $tpl->getHtmlFrag(getNodeTplName('fragments', $def['mode'], $type), [
            'items' => $items,
            'role_title' => getNodeLabel($def['title']),
            'poster' => $poster,
            'token' => getSiteToken(),
            'report_label' => _NODE_REPORT,
            'download_label' => _DOWNLOAD,
            'visit_label' => _NODE_VISIT,
            'hits_label' => _HITS,
        ]);
    }
    return $out;
}

# Render one prepared material through the view part of its display mode with the related cards, the editing link of its moderator and the data of its extension
# The live parts of a stored material - its poll, its rating and its favorite switch - are rendered by their own subsystems and handed in; a preview has none of them
function getNodeViewHtml(NodeType $type, array $view, string $rels, string $edit, array $ext = [], array $live = []): string {
    global $tpl, $fld, $prs;
    $rows = '';
    foreach ($view['fields'] as $one) $rows .= $tpl->getHtmlFrag('field-value', ['label' => $one['label_text'], 'value_html' => $one['value_html'],
        'value_text' => $one['value_text']]);
    return $tpl->getHtmlPart(getNodeTplName('partials', 'view', $type), $view + getNodeViewVars($type) + [
        'fields_html' => $rows,
        'assets_html' => getNodeAssetView($type, $view),
        'rels_html' => $rels,
        'rels_label' => _NODE_RELATED,
        'poll_html' => $live['poll'] ?? '',
        'rating_html' => $live['rating'] ?? '',
        'fav_html' => $live['fav'] ?? '',
        'edit_href' => $edit,
        'edit_label' => _EDIT,
        'ext' => $ext,
    ]);
}

# Answer the refusal of a request with its status: an unknown operation, a wrong method with the allowed ones, a bad parameter or a held type
function setNodeDeny(int $code, array $allow = []): never {
    if ($allow) header('Allow: '.implode(', ', $allow));
    if ($code === 503) header('Retry-After: 60');
    setError($code);
}

# One positive whole number of the query, 0 when it is absent; any other value refuses the request
function getNodeNumber(string $key, int $code = 400): int {
    $raw = getVar('get', $key, 'raw', '');
    if (!is_string($raw) || $raw === '') return 0;
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $raw) || (int)$raw > 4294967295) setNodeDeny($code);
    return (int)$raw;
}

# The type of the route, from the shared registry of the request; a missing, broken or, for this visitor, disabled type is not found
function getNodeRoute(): NodeType {
    global $conf;
    return getNodeTypeMap()[$conf['name']] ?? setNodeDeny(404);
}

# The list of a type with its category, letter, sort and page: a stored page is answered before any query of Node, and only the default sort of the three parameters is cached
# Explicitly sent default sort parameters are sent back to the clean address, a sort or letter the type does not allow is a bad request, and a page past the end is not found
function setNodeList(): void {
    global $conf, $tpl, $home;
    $cat = getNodeNumber('cat');
    $num = getNodeNumber('num') ?: 1;
    $let = (string)getVar('get', 'let', 'raw', '');
    $sort = (string)getVar('get', 'order', 'raw', '');
    $dir = (string)getVar('get', 'dir', 'raw', '');
    if ($let !== '' && !preg_match('/^[\p{L}\p{N}]$/Du', $let)) setNodeDeny(400);
    if ($sort !== '' && !in_array($sort, ['published', 'updated', 'title', 'views', 'rating'], true)) setNodeDeny(400);
    if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) setNodeDeny(400);
    $page = '';
    setHead(function () use ($cat, $num, $let, $sort, $dir, $home, &$page): array {
        global $conf, $tpl;
        $type = getNodeRoute();
        $priv = $type->ext === 'support';
        if ($priv && getNodeContext()->uid < 1 && !checkNodeModer($type)) setNodeDeny(403);
        $set = $type->settings['list'];
        if ($let !== '' && !$set['alpha']) setNodeDeny(400);
        if ($sort !== '' && !in_array($sort, $set['orders'], true)) setNodeDeny(400);
        if ($cat && !$type->settings['features']['categories']) setNodeDeny(404);
        $key = $sort ?: $set['order'];
        $way = $dir ?: (($key === $set['order']) ? $set['dir'] : (($key === 'title') ? 'asc' : 'desc'));
        $base = ['name' => $type->name] + ($cat ? ['cat' => $cat] : []) + ($let !== '' ? ['let' => rawurlencode($let)] : []);
        if (($sort !== '' || $dir !== '') && $key === $set['order'] && $way === $set['dir']) setRedirect(getSeoUrl($base + ($num > 1 ? ['num' => $num] : [])), false, 301);
        $cats = getCategoryMap($type->name);
        if ($cat && !isset($cats[$cat])) setNodeDeny(404);
        $query = getNodeReader($type)->setNodeType($type)->setNodePage($num, $set['limit']);
        if ($cat) $query->setNodeCategory($cat);
        if ($let !== '') $query->setNodeLetter($let);
        if ($sort !== '' || $dir !== '') $query->setNodeOrder($key, $way);
        $count = $query->getNodeCount();
        $pages = max(1, (int)ceil($count / $set['limit']));
        if ($num > $pages) setNodeDeny(404);
        $list = $count ? $query->getNodeList() : [];
        if (checkPageCache()) Cache::setPageUntil($query->getNodeDeadline());
        $hand = $list ? getNodeHandler($type) : null;
        $extm = $hand ? $hand->getNodeData($type, $list, 'list') : [];
        $items = '';
        foreach ($list as $node) {
            $view = getNodeViewData($type, $node, 'list');
            $items .= $tpl->getHtmlFrag(getNodeTplName('fragments', 'card', $type), $view + getNodeViewVars($type) + ['cover' => getNodeCover($type, $view),
                'ext' => getNodeExtVars($type, $node, $extm[$node->id] ?? [])]);
        }
        $link = static fn(int $i): array => ['href' => getSeoUrl($base + (($sort !== '' || $dir !== '') ? ['order' => $key, 'dir' => $way] : []) + ($i > 1 ? ['num' => $i] : []))];
        $title = getModuleName($type->name);
        $ctitle = $cat ? html_entity_decode(getConst($cats[$cat]['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        $page = $tpl->getHtmlPart(getNodeTplName('partials', 'list', $type), [
            'navi_html' => getNodeNavi($type, $key, $way, $cat),
            'intro' => ($cat || $num > 1 || $let !== '') ? '' : getNodeLabel($type->intro),
            'cats_html' => $type->settings['features']['categories'] ? setCategories($type->name, 1, false, (string)$cat) : '',
            'letters_html' => $set['alpha'] ? letter($type->name) : '',
            'items_html' => $items,
            'pager_html' => getTplPagerView($num, $pages, 8, $link, ['count' => $count, 'limit' => $set['limit']]),
            'empty_alert' => ['text' => _NO_INFO, 'is_warn' => false],
        ]);
        $plain = $sort === '' && $dir === '' && $let === '';
        return ['title' => ($ctitle !== '') ? $ctitle : $title, 'ctitle' => ($ctitle !== '') ? $title : '', 'cid' => $cat, 'kind' => 'collection',
            'robots' => $priv ? 'noindex, nofollow' : ($plain ? '' : 'noindex, follow'), 'desc' => ($home || $cat) ? null : getNodeLabel($type->intro)];
    });
    echo $page;
    setFoot();
}

# Whether the context moderates the type: the main administrator or a moderator named by the type
function checkNodeModer(NodeType $type): bool {
    $ask = getNodeContext();
    return $ask->super || in_array($type->name, $ask->mods, true);
}

# Whether the visitor may use the public form of the type: an active type with public submission and a workflow that admits the visitor, or its moderator
# The writer decides the same question again on every write; this answer only decides whether the form and its link are offered
function checkNodeFlow(NodeType $type): bool {
    $ask = getNodeContext();
    $flow = $type->settings['workflow'];
    $user = $ask->uid > 0;
    $open = match ($flow['access']) {
        'all' => true,
        'user' => $user,
        default => $user && array_intersect($flow['groups'], $ask->groups) !== [],
    };
    return $type->active && $type->settings['features']['submit'] && ($open || checkNodeModer($type));
}

# The state a public submission is created in: published without moderation, for a group of direct publication or for a moderator, pending otherwise
function getNodeFlowState(NodeType $type): NodeStatus {
    $ask = getNodeContext();
    $direct = $ask->uid > 0 && array_intersect($type->settings['workflow']['publish'], $ask->groups) !== [];
    return (!$type->settings['features']['moderation'] || $direct || checkNodeModer($type)) ? NodeStatus::Published : NodeStatus::Pending;
}

# The navigation of a list: the list itself, the sorts the type allows with their direction, the public form where the visitor may use it and the category switch
function getNodeNavi(NodeType $type, string $key, string $way, int $cat): string {
    $set = $type->settings['list'];
    $add = checkNodeFlow($type);
    $sort = static function (string $one, string $dir) use ($type, $cat, $set): string {
        $base = ['name' => $type->name] + ($cat ? ['cat' => $cat] : []);
        return getSeoUrl($base + (($one === $set['order'] && $dir === $set['dir']) ? [] : ['order' => $one, 'dir' => $dir]));
    };
    return getModuleNavi([
        'title' => getModuleName($type->name),
        'home_href' => getSeoUrl(['name' => $type->name]),
        'best_href' => in_array('rating', $set['orders'], true) ? $sort('rating', 'desc') : '',
        'pop_href' => in_array('views', $set['orders'], true) ? $sort('views', 'desc') : '',
        'liste_href' => '',
        'add_href' => $add ? getSeoUrl(['name' => $type->name, 'op' => 'add']) : '',
        'catshow' => $type->settings['features']['categories'] && getCategoryMap($type->name) !== [],
    ]);
}

# One material of the type by its global id: a missing, closed or foreign material is not found; a successful view is counted after the answer, never for HEAD
# The discussion follows the material where the type has comments and the material takes them; the mode the comment subsystem resolves decides whether its form is offered
# The linked poll, the shared rating of the scope node.<name> and the favorite switch appear only where the type has the feature, each rendered by its own subsystem
# A request of support is refused to a guest before anything is read and is never indexed
function setNodeView(): void {
    global $conf, $afile, $tpl, $com;
    $id = getNodeNumber('id', 404);
    if (!$id) setNodeDeny(404);
    $type = getNodeRoute();
    $priv = $type->ext === 'support';
    if ($priv && getNodeContext()->uid < 1 && !checkNodeModer($type)) setNodeDeny(403);
    $query = getNodeReader($type);
    $node = $query->getNode($id, $type);
    if ($node === null) setNodeDeny(404);
    $view = getNodeViewData($type, $node, 'view');
    $refs = [];
    foreach ($node->rels ?? [] as $rel) if ($rel->type === 'related') $refs[$rel->rid] = $type->name;
    $rels = '';
    foreach ($refs ? $query->getNodeTargetList(array_slice($refs, 0, 500, true)) : [] as $tgt) {
        $rels .= $tpl->getHtmlFrag(getNodeTplName('fragments', 'card', $type), getNodeViewData($type, $tgt, 'card') + getNodeViewVars($type) + ['cover' => '']);
    }
    $moder = checkNodeModer($type);
    $cover = getNodeCover($type, $view);
    $hand = getNodeHandler($type);
    $ext = getNodeExtVars($type, $node, $hand ? ($hand->getNodeData($type, [$node], 'view')[$node->id] ?? []) : []);
    $talk = $type->settings['features']['comments'] && $node->comon !== CommentMode::Disabled;
    $feat = $type->settings['features'];
    $poll = ($feat['poll'] && $node->poll) ? getVotingView($node->poll, $type->name) : '';
    $live = [
        'poll' => ($poll !== '') ? $tpl->getHtmlFrag('block-content', ['id' => 'rep'.$type->name, 'is_section' => true, 'content' => $poll, 'has_hr' => true]) : '',
        'rating' => $feat['rating'] ? getRatingAsync(1, $node->id, 'node.'.$type->name, $node->ratings, $node->score) : '',
        'fav' => $feat['favorites'] ? getFavoriteButton($node->id, $type->name) : '',
    ];
    setHead([
        'title' => $node->title,
        'robots' => $priv ? 'noindex, nofollow' : '',
        'ctitle' => $view['ctitle'],
        'cid' => $node->cid,
        'kind' => $type->settings['integrations']['seo'],
        'desc' => mb_substr($view['intro'], 0, 160),
        'author' => $view['author'],
        'time' => (string)$node->pubdate,
        'mtime' => $node->updated,
        'img' => ($cover !== '') ? rtrim((string)$conf['homeurl'], '/').'/'.$cover : '',
    ]);
    echo getNodeViewHtml($type, $view, $rels, $moder ? $afile.'.php?name=node&op=edit&id='.$node->id.'&type='.$type->name : '', $ext, $live)
        .($talk ? setComShow($node->id, $com->getTargetMode($type->name, $node->id)->value) : '');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        addDeferredTask(static function () use ($id, $type): void {
            try {
                getNodeWriter($type)->updateNodeViews($id, $type);
            } catch (NodeException $err) {
                if ($err->getCode() !== NodeException::NOTFOUND) Logger::addSite('error', 'Node: a view could not be counted', ['nid' => $id, 'code' => $err->getCode()]);
            }
        });
    }
    setFoot();
}

# The public form of a type: the empty form, the preview of a posted material through the view it will have, and the submission in the state the workflow gives
# A preview writes nothing; a submission is created by the writer and answered by a redirect to a safe page, the new material itself or the list with a notice
function setNodeForm(): void {
    global $conf, $tpl;
    $type = getNodeRoute();
    if (!$type->settings['features']['submit']) setNodeDeny(404);
    if (!checkNodeFlow($type)) setNodeDeny(403);
    $vals = getNodeFormVals(null);
    $errs = [];
    $note = '';
    $prev = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $act = (string)getVar('post', 'action', 'var', '');
        if (!in_array($act, ['preview', 'submit'], true)) setNodeDeny(400);
        if (!checkSiteToken((string)getVar('post', 'token', 'raw', ''))) setNodeDeny(403);
        [$input, $vals, $bad] = getNodeFormPost($type, checkNodeModer($type), null);
        $state = getNodeFlowState($type);
        if ($bad) {
            $note = implode(' ', $bad);
            http_response_code(422);
        } else {
            try {
                if ($act === 'preview') {
                    $node = getNodeWriter($type)->getNodePreview($type, $input, $state);
                    $prev = $tpl->getHtmlPart('preview', ['title' => _PREVIEW, 'body_a' => getNodeViewHtml($type, getNodeViewData($type, $node, 'view'), '', '')]);
                } else {
                    $node = getNodeWriter($type)->addNode($type, $input, $state);
                    if ($state === NodeStatus::Pending && $type->settings['workflow']['notify']['pending']) {
                        addAdminMail(true, 'node-'.$type->name, ($node->uid > 0) ? (string)$node->uname : $node->aname, $node->title);
                    }
                    if ($state === NodeStatus::Published) setRedirect(getSeoUrl(['name' => $type->name, 'op' => 'view', 'id' => $node->id]), false, 302, _NODE_ADDED);
                    setRedirect(getSeoUrl(['name' => $type->name]), false, 302, _NODE_PENDING);
                }
            } catch (NodeException $err) {
                $note = getNodeFault($err);
                $errs = getNodeFieldErrors($err);
                http_response_code(getNodeStatus($err));
            }
        }
    }
    setHead(['title' => _ADD, 'ctitle' => getModuleName($type->name)]);
    $rows = '';
    foreach (getNodeFormRows($type, $vals, $errs, checkNodeModer($type), false) as $one) {
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => $one['label'], 'label_for' => $one['for'] ?? '', 'hint' => $one['hint'] ?? '', 'hint_id' => $one['hint_id'] ?? '',
            'field_html' => $one['field']]);
    }
    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'submit', 'label_text' => _SEND, 'is_selected' => true]);
    $send = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken(), 'input_attr' => ''])
        .$tpl->getHtmlFrag('select', ['name_attr' => 'action', 'options_html' => $opts])
        .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]);
    echo $tpl->getHtmlFrag('title', ['title' => getModuleName($type->name).' - '._ADD, 'is_level_one' => true])
        .(($note !== '') ? $tpl->getHtmlFrag('alert', ['text' => htmlspecialchars($note, ENT_QUOTES, 'UTF-8'), 'is_warn' => true]) : '')
        .$prev
        .$tpl->getHtmlPart('form-add', ['action' => getSeoUrl(['name' => $type->name, 'op' => 'add']), 'form_name' => 'nodeadd', 'fields' => $rows, 'submit' => $send]);
    setFoot();
}

# The canonical file of one stored resource inside the directory of its type, outside thumb, or an empty string for anything else
function getNodeAssetPath(NodeType $type, string $src): string {
    $root = realpath(UPLOADS_DIR.'/'.$type->name);
    $full = ($root === false || $src === '') ? false : realpath($root.'/'.$src);
    if ($root === false || $full === false || !is_file($full)) return '';
    $root = str_replace('\\', '/', $root).'/';
    $full = str_replace('\\', '/', $full);
    return (str_starts_with($full, $root) && !str_starts_with(substr($full, strlen($root)), 'thumb/')) ? $full : '';
}

# One structured resource of the type through the controlled answer: an external source is a redirect after the check, a local file the shared file answer
# Only an allowed GET of a download or of an external visit is counted, right before the body or the redirect; HEAD, 304, 416 and later ranges count nothing
function setNodeAsset(): void {
    $id = getNodeNumber('id', 404);
    if (!$id) setNodeDeny(404);
    $type = getNodeRoute();
    $one = getNodeReader($type)->getNodeAsset($id, $type) ?? setNodeDeny(404);
    $mode = $type->settings['assets'][$one->role]['mode'];
    $count = in_array($mode, ['download', 'link'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    $hits = static function () use ($id, $type): void {
        try {
            getNodeWriter($type)->updateNodeAssetHits($id, $type);
        } catch (NodeException $err) {
            Logger::addSite('error', 'Node: a download could not be counted', ['aid' => $id, 'code' => $err->getCode()]);
        }
    };
    if (str_starts_with($one->src, 'http://') || str_starts_with($one->src, 'https://')) {
        if ($count) $hits();
        header('Location: '.$one->src, true, 302);
        exit;
    }
    $path = getNodeAssetPath($type, $one->src);
    if ($path === '') setNodeDeny(404);
    getFileStream($path, ($one->name !== '') ? $one->name : basename($one->src), $one->mime ?? 'application/octet-stream', $mode !== 'download', true, $count ? $hits : null);
    exit;
}

# One editor attachment of the type: a name the text of the stored material carries, or the preview of a new upload of the visitor; any refusal is the same not found
# The query must be exactly one of the two forms, so a flag, a repeated or an unknown parameter never changes which branch decides
function setNodeAttach(): void {
    $url = (string)($_SERVER['REQUEST_URI'] ?? '');
    $base = ['name' => '#^[a-z][a-z0-9]{0,19}$#D', 'op' => '#^attach$#D', 'key' => '#^.{1,255}$#Ds', 'thumb' => '#^1$#D'];
    $saved = Cache::getQueryVars($url, $base + ['id' => '#^[1-9][0-9]{0,9}$#D']);
    $fresh = Cache::getQueryVars($url, $base + ['preview' => '#^1$#D']);
    $id = isset($saved['id'], $saved['key']) ? (int)$saved['id'] : 0;
    $view = !$id && isset($fresh['preview'], $fresh['key']);
    if (!$id && !$view) setNodeDeny(404);
    $type = getNodeRoute();
    $key = (string)getVar('get', 'key', 'raw', '');
    $thumb = (string)getVar('get', 'thumb', 'raw', '') === '1';
    $path = getNodeWriter($type)->getNodeFile($type, $id, $key, $thumb);
    if ($path === '') setNodeDeny(404);
    $info = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $mime = $info ? $info->file($path) : false;
    getFileStream($path, $key, is_string($mime) ? $mime : 'application/octet-stream', true, $id > 0);
    exit;
}

# A report that a resource does not work: CSRF, one report a minute for a visitor, and the writer stores the first report once; the answer returns to the page the report came from
function setNodeReport(): void {
    global $conf;
    $id = getNodeNumber('id', 404);
    if (!$id) setNodeDeny(404);
    if (!checkSiteToken((string)getVar('post', 'token', 'raw', ''))) setNodeDeny(403);
    $type = getNodeRoute();
    $key = $conf['user_c'].'-report';
    $wait = (int)($_SESSION[$key] ?? 0) + 60 - time();
    if ($wait > 0) {
        header('Retry-After: '.$wait);
        setNodeDeny(429);
    }
    try {
        getNodeWriter($type)->updateNodeAssetReport($id, $type);
    } catch (NodeException $err) {
        setNodeDeny(in_array($err->getCode(), [NodeException::NOTFOUND, NodeException::INVALID], true) ? 404 : getNodeStatus($err));
    }
    $_SESSION[$key] = time();
    setRedirect(getSeoUrl(['name' => $type->name]), true, 302, _NODE_REPORTED);
}

# The owner closes or reopens one request of support: CSRF, the expected version of its card and the wanted state; the assignment and the priority are the stored ones,
# read from the card of the request and never from the request body, and the answer returns to the request
# The route belongs to the owner alone and to the two states open and closed, even when the owner also moderates the type; the working card is the route of the staff
function setNodeSupport(): void {
    global $conf;
    $id = getNodeNumber('id', 404);
    if (!$id) setNodeDeny(404);
    if (!checkSiteToken((string)getVar('post', 'token', 'raw', ''))) setNodeDeny(403);
    $type = getNodeRoute();
    $hand = getNodeHandler($type);
    if (!$hand instanceof NodeSupport) setNodeDeny(404);
    $raw = (string)getVar('post', 'state', 'raw', '');
    if (!ctype_digit($raw)) setNodeDeny(422);
    $tgt = getNodeReader($type)->getNodeTarget($type->name, $id) ?? setNodeDeny(404);
    $maps = $conf['node']['support']['state'] ?? [];
    if ($tgt->uid < 1 || $tgt->uid !== getNodeContext()->uid || !in_array((int)$raw, [$maps['staff'] ?? -1, $maps['closed'] ?? -1], true)) setNodeDeny(403);
    $card = $hand->getNodeData($type, [$tgt], 'view')[$id] ?? setNodeDeny(404);
    try {
        $hand->updateNodeSupport($id, $card['aid'], (int)$raw, $card['prio'], (int)getVar('post', 'version', 'num', 0));
    } catch (NodeException $err) {
        setNodeDeny(getNodeStatus($err));
    }
    setRedirect(getSeoUrl(['name' => $type->name, 'op' => 'view', 'id' => $id]), false, 302, _NODE_SSAVED);
}

if (!defined('ADMIN_FILE')) {
    $nops = getNodeOps(((string)$op === 'support') ? getNodeRoute()->ext : '');
    $nway = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $op = (string)$op;
    if (!isset($nops[$op])) setNodeDeny(404);
    if (!in_array($nway, $nops[$op], true)) setNodeDeny(405, $nops[$op]);
    $njour = getConfigJournal();
    if ($njour && ($njour['why'] === 'journal' || in_array($conf['name'], $njour['types'], true))) setNodeDeny(503);
    switch ($op) {
        default: setNodeList(); break;
        case 'view': setNodeView(); break;
        case 'add': setNodeForm(); break;
        case 'asset': setNodeAsset(); break;
        case 'attach': setNodeAttach(); break;
        case 'report': setNodeReport(); break;
        case 'support': setNodeSupport(); break;
    }
}
