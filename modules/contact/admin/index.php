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
        'ops' => ['name=contact', 'name=contact&op=info'],
        'tabs' => [_PREFERENCES, _MANUAL],
    ]);
    $rows = [
        [
            'label_html' => _CONTACTALL,
            'label_id' => $labid = getFieldIds('', 'admins')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid,
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
            'label_id' => $labid = getFieldIds('', 'info')['label'],
            'field_html' => getTplTextarea(['labelledby' => $labid, 'label' => _CONTACTINFO, 'id' => '1', 'name' => 'info', 'value' => ($conf['contact']['info'] ?? ''),
                'mod' => 'contact', 'store' => 'config', 'rows' => 10, 'placeholder' => _CONTACTINFO]),
            'is_full' => true,
        ],
    ];
    $cont .= checkPerms(CONFIG_DIR.'/contact.php');
    $body = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=contact&op=save',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken('contact')],
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
    $iswarn = !checkAdminPost('contact');
    $room = '';
    if (!$iswarn) {
        $cont = [
            'info' => getVar('post', 'info', 'text', ''),
            'admins' => getVar('post', 'admins', 'num', 0),
        ];
        $room = checkEditorTextRoom($cont['info'], 'config');
        if ($room === '') setConfigFile('contact.php', $cont);
    }
    setRedirect($afile.'.php?name=contact', false, 302, $iswarn ? _TOKENMISS : ($room ?: _SUCCSAVE), $iswarn || $room !== '');
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=contact', 'name=contact&op=info'],
        'tabs' => [_PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: contact(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
