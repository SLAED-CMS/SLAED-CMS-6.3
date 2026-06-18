<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('contact')) die('Illegal file access');

function contact(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=contact', 'name=contact&amp;op=info'],
        'tabs' => [_PREFERENCES, _DOCS],
    ]);
    $rows = [
        [
            'label_html' => _CONTACTALL,
            'field_html' => getTplRadioGroup([
                'name' => 'admins',
                'value' => (string)$conf['contact']['admins'],
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ],
        [
            'label_html' => _CONTACTINFO,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'info',
                'value_text' => $conf['contact']['info'],
                'rows_num' => 10,
            ]),
        ],
    ];
    $cont .= checkPerms(CONFIG_DIR.'/contact.php');
    $body = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=contact&amp;op=save',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $cont = [
            'info' => getVar('post', 'info', 'text', ''),
            'admins' => getVar('post', 'admins', 'num', 0),
        ];
        setConfigFile('contact.php', $cont);
    }
    setRedirect($afile.'.php?name=contact', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=contact', 'name=contact&amp;op=info'],
        'tabs' => [_PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: contact(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
