<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

require_once dirname(__DIR__).'/common.php';

# The showcase of the section of sites: the first sites of the catalogue shuffled once per request, as many as the section asks within its range
return static function (array $sec): array {
    getLang('presentation');
    $all = getPresentationSites();
    $rows = array_slice($all, 0, min(12, max(3, (int)($sec['count'] ?? 8))));
    foreach (array_keys($rows) as $i) $rows[$i] += ['rating' => _RATING, 'pos' => sprintf(_PRES_GL_OF, $i + 1, count($rows))];
    return ['sites' => $rows, 'total' => sprintf(_PRES_SI_COUNT, count($all))];
};
