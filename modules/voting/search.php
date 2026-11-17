<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The search handler of the polls, read by the search module: the rows the module itself lists, so a poll bound to a material or a closed one is never found
return static function (array $state): array {
    global $db, $conf, $locale, $afile;
    $rows = [];
    $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $state['word']).'%';
    $many = $conf['multilingual'] == 1;
    $pars = ['worda' => $like, 'wordb' => $like, 'lim' => $state['lim']] + ($many ? ['loc' => $locale] : []);
    $result = $db->getSqlQuery(
        'SELECT v.id, v.title, v.body, v.time, v.comments, v.acomm, r.path FROM '.PREFIX_DB.'_voting AS v'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'poll\' AND r.oid = v.id AND r.cur = 1'
        .' WHERE '.($many ? '(v.lang = :loc OR v.lang = \'\') AND ' : '').'v.modul = \'\' AND v.time <= NOW()'
        .' AND (v.enddate >= NOW() AND v.status = \'0\' OR v.status = \'1\')'
        .' AND (v.title LIKE :worda ESCAPE \'!\' OR v.body LIKE :wordb ESCAPE \'!\') ORDER BY v.time DESC LIMIT :lim',
        $pars
    );
    while ([$id, $title, $body, $time, $comm, $acomm, $path] = $db->getSqlRow($result)) {
        $rows[] = [
            'link' => ['name' => 'voting', 'op' => 'view', 'id' => $id, 'route' => $path],
            'edit' => HOME_PATH.$afile.'.php?name=voting&op=add&id='.$id,
            'title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'time' => $time, 'content' => str_replace('|', ', ', $body), 'safe' => true,
            'comments' => $acomm ? $comm : null,
        ];
    }
    return $rows;
};
