<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

/**
 * Base module helper (procedural-friendly).
 *
 * Goals:
 * - Reduce duplication across modules (navigation/list helpers).
 * - Keep modules procedural entry-points; class is optional.
 */
class ModuleBase
{
    protected $db;
    protected string $prefix;
    protected array $conf;
    protected array $cfg;

    public function __construct($db, string $prefix, array $conf, array $cfg)
    {
        $this->db = $db;
        $this->prefix = $prefix;
        $this->conf = $conf;
        $this->cfg = $cfg;
    }

    public function getOffset(int $limit, string $key = 'num'): array
    {
        $num = getVar('get', $key, 'num', '1');
        $num = ($num > 0) ? $num : 1;
        return array('num' => $num, 'ofs' => (int)(($num - 1) * $limit));
    }

    public function getLetterFilter(string $field, string $param = 'let'): array
    {
        $let = getVar('get', $param, 'let');
        if ($let) {
            return array(
                'let' => $let,
                'sql' => 'UCASE('.$field.') LIKE BINARY :let',
                'url' => 'op=liste&'.$param.'='.urlencode($let).'&',
                'params' => array('let' => $let.'%')
            );
        }
        return array('let' => '', 'sql' => '1=1', 'url' => 'op=liste&', 'params' => array());
    }

    public function setRedirectHome(): void
    {
        header('Location: index.php?name='.$this->conf['name']);
        exit;
    }

    /**
     * Shared navigation rendering used by many content modules.
     * Child modules may override if needed.
     */
    public function getNavigation(string $title, $cat = ''): string
    {
        $ncat = getVar('get', 'cat', 'num');
        $ncat = ($ncat) ? '&cat='.$ncat : '';

        $home = '<a href="'.getHref(array('name='.$this->conf['name'], '', '', '', '', '', '', '')).'" title="'.$this->getHomeTitle().'" class="sl_but_navi">'._HOME.'</a>';
        $best = ($this->cfg['rate'] ?? 0) ? '<a href="'.getHref(array('name='.$this->conf['name'].$ncat.'&op=best', '', '', '', '', '', '', '')).'" title="'._BEST.'" class="sl_but_navi">'._BEST.'</a>' : '';
        $pop = ($this->cfg['rate'] ?? 0) ? '<a href="'.getHref(array('name='.$this->conf['name'].$ncat.'&op=pop', '', '', '', '', '', '', '')).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>' : '';
        $liste = '<a href="'.getHref(array('name='.$this->conf['name'].'&op=liste', '', '', '', '', '', '', '')).'" title="'._LIST.'" class="sl_but_navi">'._LIST.'</a>';

        $add = ((is_user() && (($this->cfg['add'] ?? 0) == 1)) || (!is_user() && (($this->cfg['addquest'] ?? 0) == 1)))
            ? '<a href="'.getHref(array('name='.$this->conf['name'].'&op=add', '', '', '', '', '', '', '')).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>' : '';

        $catshow = ($cat) ? '<a OnClick="CloseOpen(\'sl_close_1\', 1);" title="'._CATVORH.'" class="sl_but_navi">'._CATEGORIES.'</a>' : '';

        return setTemplateBasic('navi', array(
            '{%title%}' => $title,
            '{%name%}' => $this->conf['name'],
            '{%home%}' => $home,
            '{%best%}' => $best,
            '{%pop%}' => $pop,
            '{%liste%}' => $liste,
            '{%add%}' => $add,
            '{%catshow%}' => $catshow
        ));
    }

    /**
     * Generic A-Z list page scaffold for legacy modules.
     *
     * Spec keys (all required unless noted):
     * - title (string)
     * - module (string) e.g. $this->conf['name']
     * - alias (string) e.g. 's'
     * - table (string) e.g. '_news'
     * - pk (string) e.g. 'sid'
     * - catField (string) e.g. 'catid'
     * - select (string) SELECT fields (must include pk/title/time/cat fields used by renderer)
     * - joins (string) optional JOIN clauses
     * - where (string) base WHERE (without 'WHERE')
     * - order (string) ORDER BY (without 'ORDER BY')
     * - limit (int)
     * - nump (int)
     * - letterField (string) e.g. 's.title'
     * - rowTpl (string) e.g. 'liste-basic'
     * - openTpl (string) e.g. 'liste-open'
     * - closeTpl (string) e.g. 'liste-close'
     * - headerMap (array) placeholders for openTpl
     * - renderRow (callable) function(array $row): string
     * - countWhereLetPrefix (string) e.g. 'title LIKE BINARY '
     * - countWhereSuffix (string) e.g. "AND time <= NOW() AND status != '0'"
     * - countWhereNoLet (string)
     */
    public function getListePage(array $spec): string
    {
        $this->checkListeSpec($spec);

        $flt = $this->getLetterFilter($spec['letterField']);
        $pg = $this->getOffset((int)$spec['limit'], 'num');
        $ofs = $pg['ofs'];

        $cwhere = catmids($spec['module'], $spec['alias'].'.'.$spec['catField']);

        $sql = 'SELECT '.$spec['select']
            .' FROM '.$this->prefix.$spec['table'].' AS '.$spec['alias'].' '
            .$spec['joins']
            .' WHERE '.$flt['sql'].' AND '.$spec['where'].' '.$cwhere
            .' ORDER BY '.$spec['order']
            .' LIMIT '.$ofs.', '.(int)$spec['limit'];

        $res = $this->db->sql_query($sql, $flt['params']);

        if ($this->db->sql_numrows($res) <= 0) {
            return setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
        }

        $out = setTemplateBasic($spec['openTpl'], $spec['headerMap']);

        while ($row = $this->db->sql_fetchrow($res)) {
            $out .= call_user_func($spec['renderRow'], $row);
        }

        $out .= setTemplateBasic($spec['closeTpl']);

        $let = $flt['let'];
        $onum = ($let !== '') ? $this->getListeCountWhere($spec, $let) : $this->getListeCountWhere($spec, '');

        $out .= setArticleNumbers(
            'pagenum',
            $spec['module'],
            (int)$spec['limit'],
            $flt['url'],
            $spec['pk'],
            $spec['table'],
            $spec['catField'],
            $onum,
            (int)$spec['nump']
        );

        return $out;
    }

    public function checkListeSpec(array &$spec): void
    {
        if (!array_key_exists('joins', $spec)) $spec['joins'] = '';

        $need = array(
            'title',
            'module',
            'alias',
            'table',
            'pk',
            'catField',
            'select',
            'where',
            'order',
            'limit',
            'nump',
            'letterField',
            'openTpl',
            'rowTpl',
            'closeTpl',
            'headerMap',
            'renderRow',
            'countWhereLetPrefix',
            'countWhereSuffix',
            'countWhereNoLet'
        );

        foreach ($need as $key) {
            if (!array_key_exists($key, $spec)) {
                throw new Exception('Missing liste spec key: '.$key);
            }
        }
    }

    public function getListeCountWhere(array $spec, string $let): string
    {
        # Preserve legacy LIKE BINARY behavior for count condition
        if ($let !== '') {
            $let = addslashes($let);
            return $spec['countWhereLetPrefix'].'\''.$let.'%\' '.$spec['countWhereSuffix'];
        }
        return $spec['countWhereNoLet'];
    }

    /**
     * Wrapper for legacy pagination helper.
     */
    public function getPageNumbers(array $spec): string
    {
        $need = array('module','limit','field','pk','table','catField','onum','nump');
        foreach ($need as $key) {
            if (!array_key_exists($key, $spec)) {
                throw new Exception('Missing page spec key: '.$key);
            }
        }

        return setArticleNumbers(
            'pagenum',
            $spec['module'],
            (int)$spec['limit'],
            (string)$spec['field'],
            (string)$spec['pk'],
            (string)$spec['table'],
            (string)$spec['catField'],
            (string)$spec['onum'],
            (int)$spec['nump']
        );
    }

    /**
     * Generic index page runner (loop + offset + optional pager).
     *
     * Spec keys:
     * - sql (string) must contain LIMIT {ofs}, {limit}
     * - limit (int)
     * - renderRow (callable) function(array $row): string
     * - before (string) optional
     * - after (string) optional
     * - empty (string) optional
     * - params (array) optional
     * - pager (array) optional (for getPageNumbers)
     */
    public function getIndexPage(array $spec): string
    {
        $need = array('sql','limit','renderRow');
        foreach ($need as $key) {
            if (!array_key_exists($key, $spec)) {
                throw new Exception('Missing index spec key: '.$key);
            }
        }

        $pg = $this->getOffset((int)$spec['limit'], 'num');
        $sql = str_replace(
            array('{ofs}', '{limit}'),
            array((string)$pg['ofs'], (string)((int)$spec['limit'])),
            (string)$spec['sql']
        );

        $params = array_key_exists('params', $spec) ? (array)$spec['params'] : array();
        $res = $this->db->sql_query($sql, $params);

        if ($this->db->sql_numrows($res) <= 0) {
            return array_key_exists('empty', $spec) ? (string)$spec['empty']
                : setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
        }

        $out = array_key_exists('before', $spec) ? (string)$spec['before'] : '';
        while ($row = $this->db->sql_fetchrow($res)) {
            $out .= call_user_func($spec['renderRow'], $row);
        }
        $out .= array_key_exists('after', $spec) ? (string)$spec['after'] : '';

        if (array_key_exists('pager', $spec) && is_array($spec['pager'])) {
            $out .= $this->getPageNumbers($spec['pager']);
        }

        return $out;
    }

    /**
     * Generic view page runner for legacy modules.
     *
     * Spec keys:
     * - module (string) e.g. $conf['name']
     * - alias (string) e.g. 's'
     * - table (string) e.g. '_news'
     * - pk (string) e.g. 'sid'
     * - catField (string) e.g. 'catid'
     * - select (string) SELECT fields (must include what renderer needs)
     * - joins (string) optional JOIN clauses
     * - where (string) base WHERE without pk filter (without 'WHERE')
     * - render (callable) function(int $id, array $row, ModuleBase $module): void
     * - incCounter (bool) optional, default true
     */
    public function getViewPage(array $spec): void
    {
        if (!array_key_exists('joins', $spec)) $spec['joins'] = '';

        $need = array('module','alias','table','pk','catField','select','where','render');
        foreach ($need as $key) {
            if (!array_key_exists($key, $spec)) {
                throw new Exception('Missing view spec key: '.$key);
            }
        }

        $id = getVar('get', 'id', 'num');
        if (!$id) {
            header('Location: index.php?name='.$spec['module']);
            exit;
        }

        $cwhere = catmids($spec['module'], $spec['alias'].'.'.$spec['catField']);

        $sql = 'SELECT '.$spec['select']
            .' FROM '.$this->prefix.$spec['table'].' AS '.$spec['alias'].' '
            .$spec['joins']
            .' WHERE '.$spec['alias'].'.'.$spec['pk'].' = :id AND '.$spec['where'].' '.$cwhere
            .' LIMIT 1';

        $res = $this->db->sql_query($sql, array('id' => $id));
        $row = $this->db->sql_fetchrow($res);

        if (!$row) {
            header('Location: index.php?name='.$spec['module']);
            exit;
        }

        $inc = array_key_exists('incCounter', $spec) ? (bool)$spec['incCounter'] : true;
        if ($inc) {
            $this->db->sql_query(
                'UPDATE '.$this->prefix.$spec['table'].' SET counter = counter+1 WHERE '.$spec['pk'].' = :id',
                array('id' => $id)
            );
        }

        call_user_func($spec['render'], (int)$id, $row, $this);
    }

    public function getHomeTitle(): string
    {
        $name = (string)($this->conf['name'] ?? '');
        if ($name) {
            $const = '_'.strtoupper($name);
            if (defined($const)) return constant($const);
        }
        return $name;
    }
}
