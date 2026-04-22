<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getStatisticSearch(): string {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $files = [];
    foreach (scandir(COUNTER_DIR.'/statistic/') as $filev) $files[] = $filev;
    rsort($files);
    $sopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _NO_INFO,
        'is_selected' => !$file,
    ]);
    foreach ($files as $val) {
        if ($val != '' && preg_match('/^statistic\_(.+)\.log/', $val, $matches)) {
            $sopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => $val,
                'label_text' => $matches[1],
                'is_selected' => $file === $val,
            ]);
        }
    }
    $sel = $tpl->getHtmlFrag('select', [
        'name_attr' => 'file',
        'options_html' => $sopts,
    ]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'statistic'],
        ],
        'content_html' => _STATFROM.': '.$sel.' '.$tpl->getHtmlFrag('button', [
            'submit_label' => _OK,
            'button_type' => 'submit',
        ]),
    ]);
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
}

function statistic(): void {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&amp;file='.$file : '';
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    $statv = $tpl->getHtmlFrag('image-preview', [
        'alt_text' => _STATGR,
        'image_id' => 'statistic-chart-main',
        'src_attr' => $afile.'.php?name=statistic'.($file ? '&file='.$file : '').'&op=add&day=15',
    ]);
    if ($file || date('d') > 15) {
        if ($file) {
            $path = COUNTER_DIR.'/statistic/'.$file;
            $temp = (is_file($path) && is_readable($path)) ? file($path) : [];
            $out = ($temp !== false) ? count($temp) : 0;
        } else {
            $out = date('d');
        }
        $statv .= $tpl->getHtmlFrag('image-preview', [
            'alt_text' => _STATGR,
            'image_id' => 'statistic-chart-extra',
            'src_attr' => $afile.'.php?name=statistic'.($file ? '&file='.$file : '').'&op=add&day='.$out,
        ]);
    }
    $head = [
        ['content' => _DATE],
        ['content' => _UNIQUE],
        ['content' => _HITS],
        ['content' => _HOME],
        ['content' => _REFERERS],
        ['content' => _BOTSOPT],
        ['content' => _AUDIENCE],
        ['content' => _USERS],
    ];
    $rows = '';
    $daysLog = COUNTER_DIR.'/days.log';
    $statLog = COUNTER_DIR.'/statistic.log';
    if ($file) {
        $path = COUNTER_DIR.'/statistic/'.$file;
        $f = (is_file($path) && is_readable($path)) ? file($path) : [];
    } else {
        if (file_exists($daysLog) && is_readable($daysLog)) {
            $f = file($daysLog);
            $stat = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : false;
            if ($stat !== false) $f = array_merge($f, $stat);
        } else {
            $f = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : [];
        }
    }
    $f = ($f !== false) ? $f : [];
    $to = count($f);
    $unique = $today = $engines = $sites = $homepage = $auditory = $regusers = 0;
    for ($i = 0; $i < $to; $i++) {
        $out = explode('|', $f[$i]);
        $unique += $out[1];
        $today += $out[2];
        $engines += $out[4];
        $sites += $out[5];
        $homepage += $out[6];
        $out_aud = $out[1] - ($out[4] + $out[5]);
        $auditory += $out_aud;
        if ($auditory < 0) $auditory = 0;
        $regusers += rtrim($out[7]);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $out[0]],
                ['content_html' => $out[1]],
                ['content_html' => $out[2]],
                ['content_html' => $out[6]],
                ['content_html' => $out[5]],
                ['content_html' => $out[4]],
                ['content_html' => (string)$out_aud],
                ['content_html' => rtrim($out[7])],
            ],
        ])]);
    }
    $rows .= $tpl->getHtmlFrag('table-row', [
        'row_attr' => 'data-sort-method="none"',
        'cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _ALL])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$unique])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$today])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$homepage])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$sites])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$engines])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$auditory])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$regusers])],
            ],
        ]),
    ]);
    $statv .= $tpl->getHtmlFrag('table', [
        'is_wrapless' => true,
        'head' => $head,
        'rows_html' => $rows,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $statv]);
    setFoot();
}

function add(): void {
    getStatistic();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'tab' => 1,
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $rows = [
        ['label_html' => _STATBET, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'bet', 'value_attr' => (string)$conf['statistic']['bet'], 'is_config' => true])],
        ['label_html' => _STATSHI, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'shi', 'value_attr' => (string)$conf['statistic']['shi'], 'is_config' => true])],
        ['label_html' => _STATACT, 'field_html' => getTplRadioGroup(['name' => 'stat', 'value' => (string)(int)$conf['statistic']['stat'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'statistic'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [
            'bet' => getVar('post', 'bet', 'num', 42),
            'shi' => getVar('post', 'shi', 'num', 22),
            'stat' => getVar('post', 'stat', 'num')
        ];
        setConfigFile('statistic.php', $cont);
    }
    setRedirect($afile.'.php?name=statistic&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'subtitle_html' => getStatisticSearch(),
    ]);
}

switch ($op) {
    default: statistic(); break;
    case 'add': add(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
