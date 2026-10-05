<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorPlain implements ContentDriver {
    public function getAssets(string $profile): string {
        return '';
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        return $tpl->getHtmlFrag('textarea', [
            'name_attr' => $name,
            'rows_num' => $rows,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'input_id' => $id,
            'placeholder_text' => (string)($data['placeholder'] ?? ''),
            'is_required' => !empty($data['required']),
            'labelledby' => (string)($data['labelledby'] ?? ''),
            'aria_label' => (string)($data['arialabel'] ?? ''),
            'describedby' => (string)($data['describedby'] ?? ''),
        ]);
    }
}
