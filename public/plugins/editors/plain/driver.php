<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorPlain implements ContentDriver {
    # Plain carries no engine, so it has no asset of its own; the runtime of the shell comes with the frame
    public function getAssets(string $profile): string {
        return '';
    }

    # Render the textarea in the frame of the shell, its height given by rows and its format named in the status line
    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        $data['rows'] = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        return Editor::getFrame(['id' => $id, 'name' => $name, 'value' => $value, 'lang' => (string)($data['format'] ?? 'plain')] + $data);
    }
}
