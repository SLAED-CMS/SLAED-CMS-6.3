<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function fields(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['', '', '', '', '', '', 'name=fields&amp;op=info'], 'tabs' => [_ACCOUNT, _CONTENT, _FORUM, _HELP, _NEWS, _ORDER, _INFO], 'id' => 'fields']);
    $cont .= checkPerms(CONFIG_DIR.'/fields.php');
    $mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
    $content = '';
    $k = 0;
    foreach ($mods as $val) {
        $fieldc = explode('||', $conf['fields'][$val]);
        $tabcontent = '';
        for ($c = 0; $c < 10; $c++) {
            preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $fieldc[$c], $out);
            $fieldname = [_FIELDINPUT, _FIELDAREA, _FIELDSELECT, _FIELDTIME, _FIELDDATE];
            $fopts = '';
            foreach ($fieldname as $key => $val2) {
                $fopts .= getTplOption((string)($key + 1), $val2, $out[3] == ($key + 1));
            }
            $field = getTplSelect('field3'.$k.'[]', $fopts, 'sl_conf');
            $fieldname2 = [_FIELDIN, _FIELDOUT];
            $fopts2 = '';
            foreach ($fieldname2 as $key => $val3) {
                $fopts2 .= getTplOption((string)($key + 1), $val3, $out[4] == ($key + 1));
            }
            $field2 = getTplSelect('field4'.$k.'[]', $fopts2, 'sl_conf');
            $b = $c + 1;
            $tabcontent .= $tpl->getHtmlFrag('admin-fields-field-block', [
                'block_id' => 'fi'.$k.$c,
                'content_value' => $out[2] ?? '',
                'display_attr' => (empty($out[1]) && $c != 0) ? ' class="sl_none"' : '',
                'field_html' => $field,
                'field2_html' => $field2,
                'hr_html' => ($c == '0') ? '' : getTplHrLine(),
                'next_block_id' => 'fi'.$k.$b,
                'content_placeholder' => _CONTENT,
                'content_label' => _CONTENT.':',
                'field_label' => _FIELD.': '.$b,
                'name_label' => _NAME.':',
                'name_placeholder' => _NAME,
                'name_value' => $out[1] ?? '',
                'type_label' => _TYPE.':',
                'uses_label' => _USES.':',
                'title_attr' => _ADD,
                'xid' => (string)$k,
            ]);
        }
        $content .= $tpl->getHtmlFrag('admin-fields-tab-content', [
            'items_html' => $tabcontent,
            'tab_id' => getTplAdminTabName('fields', $k),
        ]);
        $k++;
    }
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _FIELDINFO]);
    $fieldv = getTplAdminConfSave($content, 'fields', 'save')
       .getTplAdminTabsSetup('fields');
    echo $cont.getTplBox($fieldv);
    setFoot();
}

function save(): void {
    global $afile;
    $cont = [];
    $mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
    $a = 0;
    foreach ($mods as $val) {
        $fields = '';
        for ($i = 0; $i < 10; $i++) {
            $ident = ($i == 0) ? '' : '||';
            $field1 = getVar('post', 'field1'.$a.'['.$i.']', 'var', 0);
            $field2 = getVar('post', 'field2'.$a.'['.$i.']', 'var', 0);
            $field3 = getVar('post', 'field3'.$a.'['.$i.']', 'var', 0);
            $field4 = getVar('post', 'field4'.$a.'['.$i.']', 'var', 0);
            $fields .= $ident.$field1.'|'.$field2.'|'.$field3.'|'.$field4;
        }
        $a++;
        $cont[$val] = $fields;
    }
    setConfigFile('fields.php', $cont);
    setRedirect($afile.'.php?name=fields');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=fields', 'name=fields', 'name=fields', 'name=fields', 'name=fields', 'name=fields', 'name=fields&amp;op=info'], 'tabs' => [_ACCOUNT, _CONTENT, _FORUM, _HELP, _NEWS, _ORDER, _INFO], 'tab' => 6]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: fields(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
