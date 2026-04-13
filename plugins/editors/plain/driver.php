<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorPlain implements ContentDriver {
    public function getAssets(string $profile): string {
        return '';
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $placeholder = (string)($data['placeholder'] ?? '');
        $required = !empty($data['required']);
        $cls = defined('ADMIN_FILE') ? 'sl_field sl-form-control' : (($profile === 'full') ? 'sl_form' : 'sl_field');
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $eval = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $ph = $placeholder !== '' ? ' placeholder="'.htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8').'"' : '';
        $req = $required ? ' required' : '';
        return '<textarea id="'.$eid.'" name="'.$enm.'" rows="'.$rows.'" class="'.$cls.'"'.$ph.$req.'>'.$eval.'</textarea>';
    }
}
