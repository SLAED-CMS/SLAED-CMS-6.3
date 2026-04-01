<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function replace(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['', '', 'name=replace&amp;op=info'], 'tabs' => [_CONTENT, _NEWS, _INFO], 'id' => 'replace']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _REPLACEINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/replace.php');
    $mods = ['content', 'news'];
    $content = '';
    $k = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $items = '';
            $fieldc = explode('||', $conf['replace'][$val]);
            for ($c = 0; $c < 50; $c++) {
                preg_match('#(.*)\|(.*)#i', $fieldc[$c], $out);
                $b = $c + 1;
                $display = (empty($out[1]) && empty($out[1][$c]) != '0' && $c != '0') ? ' class="sl_none"' : '';
                $items .= $tpl->getHtmlFrag('admin-replace-field-block', [
                    'block_id' => 'fi'.$k.$c,
                    'content_label' => _CONTENT.':',
                    'content_placeholder' => _CONTENT,
                    'content_value' => $out[2] ?? '',
                    'display_attr' => $display,
                    'hr_html' => ($c === 0) ? '' : getTplHrLine(),
                    'info_text' => _REPLACEIN,
                    'next_block_id' => 'fi'.$k.$b,
                    'title_attr' => _ADD,
                    'title_text' => _REPLACE_FIELD.': '.$b,
                    'word_label' => _WORD.':',
                    'word_placeholder' => _WORD,
                    'word_value' => $out[1] ?? '',
                    'xid' => (string)$k,
                ]);
            }
            $content .= $tpl->getHtmlFrag('admin-tab-content', [
                'items_html' => $items,
                'tab_id' => getTplAdminTabName('replace', $k),
            ]);
            $k++;
        }
    }
    $repv = getTplAdminConfSave($content, 'replace', 'save')
        .getTplAdminTabsSetup('replace');
    echo $cont.getTplBox($repv);
    setFoot();
}

function save(): void {
    global $afile;
    $cont = [];
    $mods = ['content', 'news'];
    $a = 0;
    foreach ($mods as $val) {
        $fields = '';
        for ($i = 0; $i < 50; $i++) {
            $ident = ($i == 0) ? '' : '||';
            $field1 = getVar('post', 'field1'.$a.'['.$i.']', 'word', '0');
            $field2 = getVar('post', 'field2'.$a.'['.$i.']', '', '0');
            $fields .= $ident.$field1.'|'.$field2;
        }
        $a++;
        $cont[$val] = $fields;
    }
    setConfigFile('replace.php', $cont);
    setRedirect($afile.'.php?name=replace');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=replace', 'name=replace', 'name=replace&amp;op=info'], 'tabs' => [_CONTENT, _NEWS, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: replace(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
