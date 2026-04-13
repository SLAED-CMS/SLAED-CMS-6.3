<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorTinymce implements ContentDriver {
    private static bool $done = false;
    private const PL_FULL = 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount';
    private const PL_SIMPLE = 'lists link';
    private const TB_FULL = 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code';
    private const TB_SIMPLE = 'bold italic | link | bullist numlist';

    public function getAssets(string $profile): string {
        if (self::$done) return '';
        self::$done = true;
        return getHtmlScriptSrc('plugins/editors/tinymce/assets/tinymce.min.js');
    }

    public function getWidget(string $id, string $name, string $value, string $profile): string {
        $jid = json_encode('#'.$id);
        $lang = substr(_LOCALE, 0, 2);
        $pl = ($profile === 'full') ? self::PL_FULL : self::PL_SIMPLE;
        $tb = ($profile === 'full') ? self::TB_FULL : self::TB_SIMPLE;
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $eval = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $ta = '<textarea id="'.$eid.'" name="'.$enm.'">'.$eval.'</textarea>';
        $cfg = '{selector:'.$jid.',license_key:"gpl",language:"'.$lang.'",plugins:"'.$pl.'",toolbar:"'.$tb.'",promotion:false,branding:false}';
        return $ta.getHtmlScriptInline('tinymce.init('.$cfg.');');
    }
}
