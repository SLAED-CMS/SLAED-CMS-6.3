<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorPlain implements ContentDriver {
    public function getAssets(string $profile): string {
        return '';
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $placeholder = (string)($data['placeholder'] ?? '');
        $required = !empty($data['required']);
        $attr = 'id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"';
        if ($placeholder !== '') $attr .= ' placeholder="'.htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8').'"';
        if ($required) $attr .= ' required';
        return $tpl->getHtmlFrag('new/textarea', [
            'name_attr' => $name,
            'rows_num' => $rows,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'input_attr' => $attr,
        ]);
    }
}
