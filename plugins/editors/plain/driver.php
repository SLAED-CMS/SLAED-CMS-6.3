<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorPlain implements ContentDriver {
    public function getAssets(string $profile): string {
        return '';
    }

    public function getWidget(string $id, string $name, string $value, string $profile): string {
        $rows = ($profile === 'full') ? '20' : '10';
        $cls = ($profile === 'full') ? 'sl_form' : 'sl_field';
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $eval = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return '<textarea id="'.$eid.'" name="'.$enm.'" rows="'.$rows.'" class="'.$cls.'">'.$eval.'</textarea>';
    }
}
