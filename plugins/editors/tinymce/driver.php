<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorTinymce implements ContentDriver {
    private static bool $done = false;
    private const BASE_URL = '/plugins/editors/tinymce/assets';
    private const PL_FULL = 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount';
    private const PL_SIMPLE = 'lists link';
    private const TB_FULL = 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code';
    private const TB_SIMPLE = 'bold italic | link | bullist numlist';

    public function getAssets(string $profile): string {
        global $tpl;
        if (self::$done) return '';
        self::$done = true;
        return $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/tinymce/assets/tinymce.min.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $jid = json_encode($id);
        $jph = json_encode((string)($data['placeholder'] ?? ''));
        $jlab = json_encode((string)($data['label'] ?? ''));
        $pl = ($profile === 'full') ? self::PL_FULL : self::PL_SIMPLE;
        $tb = ($profile === 'full') ? self::TB_FULL : self::TB_SIMPLE;
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $ta = $tpl->getHtmlFrag('textarea', [
            'name_attr' => $name,
            'rows_num' => $rows,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'labelledby' => (string)($data['labelledby'] ?? ''),
            'aria_label' => (string)($data['arialabel'] ?? ''),
            'describedby' => (string)($data['describedby'] ?? ''),
            'input_attr' => 'id="'.$eid.'"',
        ]);
        $js = '(function(){var el=document.getElementById('.$jid.');';
        $js .= 'if(!el||typeof tinymce==="undefined"){return;}';
        $js .= 'tinymce.init({target:el,placeholder:'.$jph.',license_key:"gpl",base_url:"'.self::BASE_URL.'",';
        $js .= 'suffix:".min",icons:"default",plugins:"'.$pl.'",toolbar:"'.$tb.'",skin:"oxide",';
        $js .= 'promotion:false,branding:false,menubar:false,statusbar:true,';
        $js .= 'init_instance_callback:function(ed){var box=ed.getBody&&ed.getBody();if(box){box.setAttribute("aria-label",'.$jlab.');}}});';
        $js .= '})();';
        return $ta.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
