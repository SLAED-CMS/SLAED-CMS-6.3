<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorCodemirror implements ContentDriver, CodeDriver {
    private static bool $done = false;
    private const LANGS = ['php', 'html', 'css', 'js', 'json', 'sql', 'xml', 'markdown', 'ini', 'apache', 'robots'];

    # Print the stylesheet of the plugin once; the scripts are modules the runtime imports when an editor comes into view
    public function getAssets(string $profile): string {
        if (self::$done) return '';
        self::$done = true;
        return Editor::getAssetTags(['plugins/editors/codemirror/assets/cm6.css'], []);
    }

    # Render the frame with the core and the one grammar needed: a text in its format for the data of getContent(), code for the label of getCode()
    public function getWidget(string $id, string $name, string $value, string $mode, array|string $data = [], string $label = ''): string {
        $dir = 'plugins/editors/codemirror/assets/';
        $text = is_array($data);
        $lang = $text ? (string)(($data['format'] ?? '') ?: 'markdown') : $mode;
        $base = $text ? $data + ['rows' => ($mode === 'full') ? 20 : 10] : ['label' => $label, 'arialabel' => $label, 'code' => true];
        return Editor::getFrame([
            'id' => $id,
            'name' => $name,
            'value' => $value,
            'lang' => $lang,
            'core' => Template::getAssetUrl($dir.'core.js'),
            'grammar' => in_array($lang, self::LANGS, true) ? Template::getAssetUrl($dir.'lang-'.$lang.'.js') : '',
        ] + $base);
    }
}
