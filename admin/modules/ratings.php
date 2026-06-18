<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function ratings(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=ratings', 'name=ratings&amp;op=info'], 'tabs' => [_HOME, _DOCS]]);
    $cont .= checkPerms(CONFIG_DIR.'/ratings.php');
    $mods = ['account', 'faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    $blocks = '';
    foreach ($mods as $i => $val) {
        $con = explode('|', $conf['ratings'][$val]);
        $blocks .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'ratings'.$i,
            'label_html' => getModuleName($val).' - '.$val,
            'content_html' => $tpl->getHtmlPart('div', [
                'rows' => [
                    [
                        'label_html' => _VOTING_TIME,
                        'is_ratings_inner' => true,
                        'field_html' => $tpl->getHtmlFrag('input', [
                            'itype' => 'number',
                            'name_attr' => 'time['.$i.']',
                            'value_attr' => (string) intval($con[0] / 86400),
                            'is_config' => true,
                            'is_ratings_days' => true,
                        ]),
                    ],
                    [
                        'label_html' => _C_21,
                        'is_ratings_inner' => true,
                        'field_html' => getTplRadioGroup([
                            'name' => $i.'in',
                            'value' => (string) $con[1],
                            'options' => [
                                ['value' => '1', 'label' => _YES],
                                ['value' => '0', 'label' => _NO],
                            ],
                        ]),
                    ],
                    [
                        'label_html' => _C_22,
                        'is_ratings_inner' => true,
                        'field_html' => getTplRadioGroup([
                            'name' => $i.'view',
                            'value' => (string) $con[2],
                            'options' => [
                                ['value' => '1', 'label' => _YES],
                                ['value' => '0', 'label' => _NO],
                            ],
                        ]),
                    ],
                ],
            ]),
        ]);
    }
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'ratings'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $blocks,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    $content = [];
    $mods = ['account', 'faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    if (!$warn) {
        foreach ($mods as $i => $val) {
            $time_days = getVar('post', 'time['.$i.']', 'num', 0);
            $time = $time_days > 0 ? $time_days * 86400 : 2592000;
            $in = getVar('post', $i.'in', 'num', 0);
            $view = getVar('post', $i.'view', 'num', 0);
            $content[$val] = $time.'|'.$in.'|'.$view;
        }
        setConfigFile('ratings.php', $content);
    }
    setRedirect($afile.'.php?name=ratings', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=ratings', 'name=ratings&amp;op=info'],
        'tabs' => [_HOME, _DOCS],
    ]);
}

switch ($op) {
    default: ratings(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
