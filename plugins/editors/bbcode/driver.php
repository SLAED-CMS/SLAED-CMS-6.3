<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorBbcode implements ContentDriver {
    public function getAssets(string $profile): string {
        global $tpl;
        return $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/system/insert-code.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return getTplBbEditor([
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'rows' => (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10)),
            'placeholder' => (($data['placeholder'] ?? '') !== '') ? ' placeholder="'.htmlspecialchars((string)$data['placeholder'], ENT_QUOTES, 'UTF-8').'"' : '',
            'required' => !empty($data['required']) ? ' required' : '',
            'stloc' => (string)($data['stloc'] ?? substr(_LOCALE, 0, 2)),
            'mod' => (string)($data['mod'] ?? ''),
            'con' => (array)($data['con'] ?? []),
        ]);
    }
}
