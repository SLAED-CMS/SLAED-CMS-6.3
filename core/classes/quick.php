<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Quick edit: one in-place edit of a stored text shared by every kind that offers it, with the closed forms of a subject, the order of a save and its closed result codes
class QuickEdit {

    # The closed form of an item id; the field and the stamp have the closed forms of their kind
    private const ID = '/^[1-9][0-9]{0,9}$/D';

    # The result codes a write may answer; anything else an adapter answers is taken as a failed write
    private const CODES = ['saved', 'denied', 'unavailable', 'conflict', 'rules', 'storage', 'blocked'];

    private array $kinds;

    # Build the protocol from the adapters of the kinds, each a list of its fields, the closed form of its stamp and the closures source, write and view
    # A kind takes part through three closures, the way the rating subsystem is built from its read and write closures; nothing here knows a table or a column
    # The source answers the right, the stored text, the stamp and the rendered editor from the stored row; the write stores at a stamp under the lock of the kind
    # The view renders the stored text exactly as the page renders it, together with the edited mark the page always prints, so a save can refresh both
    public function __construct(array $kinds) {
        $this->kinds = $kinds;
    }

    # The stamp of a stored text that has no version column: the text hashed with its edit time, so an edit through any path, the quick one or a full form, changes it
    public static function getStamp(string $text, string $time): string {
        return sha1($text."\0".$time);
    }

    # Resolve the subject a request names from its raw values, or null as soon as one of them leaves its closed form; the stamp is checked only when a save names one
    public function getSubject(mixed $kind, mixed $id, mixed $field, mixed $stamp = null): ?array {
        $one = (is_string($kind) && isset($this->kinds[$kind])) ? $this->kinds[$kind] : null;
        if ($one === null || !is_string($id) || !preg_match(self::ID, $id) || !in_array($field, $one['fields'], true)) return null;
        if ($stamp !== null && (!is_string($stamp) || !preg_match($one['stamp'], $stamp))) return null;
        return ['kind' => $kind, 'id' => intval($id), 'field' => $field, 'stamp' => (string)$stamp];
    }

    # Answer the editor of one subject: unavailable for a gone item, denied without the right, otherwise ready with the stamp and the editor its kind rendered
    public function getEditor(array $sub): array {
        $src = ($this->kinds[$sub['kind']]['source'])($sub['id'], $sub['field']);
        if ($src === null) return ['code' => 'unavailable'];
        if (empty($src['allow'])) return ['code' => 'denied'];
        return ['code' => 'ready', 'stamp' => (string)$src['stamp'], 'editor' => (string)$src['editor']];
    }

    # Store one text at the stamp its editor was opened with and answer the closed code; a save and a conflict carry the stored text rendered, its mark and its current stamp
    # A save may carry a note for its author, such as a material sent back to moderation by the edit
    # The order of the checks under the lock belongs to the writer of the kind: existence, the right, an equal text answering saved without a write, then the stamp
    public function updateText(array $sub, string $text): array {
        $one = $this->kinds[$sub['kind']];
        $res = ($one['write'])($sub['id'], $sub['field'], $sub['stamp'], $text);
        $code = in_array($res['code'] ?? '', self::CODES, true) ? $res['code'] : 'storage';
        $out = ['code' => $code, 'error' => (array)($res['error'] ?? []), 'note' => (string)($res['note'] ?? '')];
        if ($code !== 'saved' && $code !== 'conflict') return $out;
        $view = ($one['view'])($sub['id'], $sub['field']);
        if (!$view) return ['code' => 'unavailable', 'error' => []];
        return $out + ['html' => (string)$view['html'], 'mark' => (string)$view['mark'], 'stamp' => (string)$view['stamp']];
    }
}
