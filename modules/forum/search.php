<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The search handler of the forum, read by the search module: the topics of the categories the visitor may open, matched by their title and their first message
return static function (array $state): array {
    global $db;
    $rows = [];
    $cats = getForumCats();
    if (!$cats) return $rows;
    $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $state['word']).'%';
    $cond = 'f.cid IN ('.implode(', ', $cats).') AND f.status '.(is_moder('forum') ? "!= '0'" : "> '1'").' AND';
    $pars = ['worda' => $like, 'wordb' => $like, 'lim' => $state['lim']];
    $result = $db->getSqlQuery(
        'SELECT f.id, f.pid, f.name, f.title, f.time, f.cid, f.body, f.comments, f.counter, u.name, r.path, ur.path'
        .' FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS ur ON ur.kind = \'user\' AND ur.oid = u.id AND ur.cur = 1'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'topic\' AND r.oid = f.id AND r.cur = 1'
        .' WHERE '.$cond.' f.pid = \'0\' AND f.time <= NOW() AND (f.title LIKE :worda ESCAPE \'!\' OR f.body LIKE :wordb ESCAPE \'!\')'
        .' ORDER BY f.time DESC LIMIT :lim',
        $pars
    );
    while ([$mid, $pid, $user, $titl, $time, $cid, $cont, $comm, $reads, $nick, $path, $upath] = $db->getSqlRow($result)) {
        $tid = !$pid ? $mid : $pid;
        $rows[] = [
            'link' => ['name' => 'forum', 'op' => 'view', 'id' => $tid, 'route' => $path],
            'edit' => getSeoUrl(['name' => 'forum', 'op' => 'add', 'id' => $tid, 'cat' => $cid, 'pid' => $pid]),
            'title' => $titl, 'time' => $time, 'cid' => $cid, 'content' => $cont, 'safe' => true, 'trust' => true,
            'nick' => $nick, 'upath' => $upath, 'user' => $user, 'post' => true, 'comments' => $comm, 'reads' => $reads,
        ];
    }
    return $rows;
};
