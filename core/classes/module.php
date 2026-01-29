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
