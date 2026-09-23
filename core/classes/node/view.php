<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The one preparer of the Node views: a read material or a light target becomes the exact data array of one closed display mode, never HTML, never a query, never a template call
# Text goes through the shared parser with the controlled attachment route of the stored material, field values through the shared field system, addresses through getSeoUrl()
# Trusted rendering is granted only to a text that still carries a trusted tag, because every write strips those tags from anyone but the main administrator
final class NodeView {

    # The modes a full material may be prepared for, and the modes a light target may be prepared for
    private const FULL = ['list', 'view', 'card'];
    private const LIGHT = ['card', 'block', 'search'];

    # The modes of a role whose external source is shown where it lives; a download or a link keeps the counting route even for an external address
    private const OUTSIDE = ['image', 'gallery', 'player', 'none'];

    private Parser $prs;
    private Field $fld;

    # Keep the shared parser and the shared field system the texts and values are prepared with
    public function __construct(Parser $prs, Field $field) {
        $this->prs = $prs;
        $this->fld = $field;
    }

    # Prepare one accessible material or target of the type for one closed mode; an unknown mode, a pair of object and mode outside the contract or another type is refused
    public function getNodeView(NodeType $type, Node|NodeTarget $node, string $mode): array {
        $full = $node instanceof Node;
        if (!in_array($mode, $full ? self::FULL : self::LIGHT, true)) throw new NodeException('Invalid node input: mode', NodeException::INVALID);
        if (($full ? $node->tid : $node->type->id) !== $type->id) throw new NodeException('Invalid node input: type', NodeException::INVALID);
        return $full ? $this->getFullView($type, $node, $mode) : $this->getTargetView($type, $node, $mode);
    }

    # The public address of one material of the type, empty for an unsaved one, with the title as the slug where the site rewrites addresses
    private function getViewHref(NodeType $type, int $id, string $title): string {
        return ($id > 0) ? getSeoUrl(['name' => $type->name, 'op' => 'view', 'id' => $id, 'title' => $title]) : '';
    }

    # Render one stored text; trusted rendering only for a text a trusted tag proves the main administrator wrote, and the attachments of a saved material through its route
    private function getTextHtml(string $src, string $mod, int $nid, int $hoff): string {
        if ($src === '') return '';
        $safe = !preg_match('#\[/?use(?:html|php)\]#i', $src);
        return $this->prs->filterContent($src, $safe, $mod, $hoff, '', $nid);
    }

    # The plain text of rendered HTML: tags removed, entities decoded, white space folded
    private function getPlainText(string $html): string {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    # A stored database date as an ISO 8601 moment, or an empty string when there is none
    private function getIsoDate(?string $date): string {
        $time = ($date === null || $date === '') ? false : strtotime($date);
        return ($time === false) ? '' : date('c', $time);
    }

    # The average of the stored aggregate exactly as the shared rating answers it: up to six fraction digits by long division on integers, rounded half up, no trailing zeros
    private function getAverage(int $score, int $num): ?string {
        if ($num < 1) return null;
        $rest = $score % $num;
        $micro = intdiv($score, $num) * 1000000;
        for ($i = 0, $unit = 100000; $i < 6; $i++, $unit = intdiv($unit, 10)) {
            $rest *= 10;
            $micro += intdiv($rest, $num) * $unit;
            $rest %= $num;
        }
        if ($rest * 2 >= $num) $micro++;
        return rtrim(rtrim(intdiv($micro, 1000000).'.'.str_pad((string)($micro % 1000000), 6, '0', STR_PAD_LEFT), '0'), '.');
    }

    # The address of one resource: the counting route of a saved one, the source of an external one a role shows where it lives,
    # and for an unsaved preview the protected preview of its own upload; a nested local path has no preview address
    private function getAssetHref(NodeType $type, NodeAsset $one, string $mode, bool $link): string {
        if ($link && in_array($mode, self::OUTSIDE, true)) return $one->src;
        if ($one->id > 0) return getSeoUrl(['name' => $type->name, 'op' => 'asset', 'id' => $one->id]);
        if ($link) return $one->src;
        return ($one->src === basename($one->src)) ? 'index.php?name='.$type->name.'&op=attach&key='.rawurlencode($one->src).'&preview=1' : '';
    }

    # The resources of a material grouped by role in the order of the roles of the type; an inactive or unknown role stays out, the source, the report and its author never leave
    private function getAssetView(NodeType $type, array $list): array {
        $roles = $type->settings['assets'];
        $out = array_fill_keys(array_keys(array_filter($roles, fn($v) => $v['active'])), []);
        foreach ($list as $one) {
            $def = $roles[$one->role] ?? null;
            if ($def === null || !$def['active']) continue;
            $link = str_starts_with($one->src, 'http://') || str_starts_with($one->src, 'https://');
            $out[$one->role][] = [
                'id' => $one->id,
                'kind' => $one->kind,
                'role' => $one->role,
                'href' => $this->getAssetHref($type, $one, $def['mode'], $link),
                'rhref' => ($one->id > 0 && $def['report']) ? getSeoUrl(['name' => $type->name, 'op' => 'report', 'id' => $one->id]) : '',
                'name' => ($one->name !== '') ? $one->name : ($link ? '' : basename($one->src)),
                'title' => $one->title,
                'intro' => $one->intro,
                'mime' => $one->mime,
                'size' => $one->size,
                'stext' => ($one->size !== null) ? filterSize($one->size) : '',
                'width' => $one->width,
                'height' => $one->height,
                'duration' => $one->duration,
                'hits' => $one->hits,
                'islink' => $link,
            ];
        }
        return array_filter($out);
    }

    # The array of a full material: texts, author, category, dates, counters, the field values and the resources the read loaded
    private function getFullView(NodeType $type, Node $node, string $mode): array {
        $hoff = ($mode === 'view') ? 1 : 2;
        $intro = $this->getTextHtml($node->intro, $type->name, $node->id, $hoff);
        $user = ($node->uid > 0) ? (string)$node->uname : '';
        $cat = ($node->cid > 0 && $node->ctitle !== null) ? html_entity_decode($node->ctitle, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
        return [
            'id' => $node->id,
            'type' => $type->name,
            'mode' => $mode,
            'href' => $this->getViewHref($type, $node->id, $node->title),
            'title' => $node->title,
            'intro' => $this->getPlainText($intro),
            'intro_html' => $intro,
            'body_html' => ($mode === 'view' && $node->body !== null) ? $this->getTextHtml($node->body, $type->name, $node->id, $hoff) : '',
            'author' => ($node->uid > 0) ? $user : $node->aname,
            'ahref' => ($user !== '') ? getSeoUrl(['name' => 'account', 'op' => 'view', 'uname' => rawurlencode($user)]) : '',
            'ctitle' => $cat,
            'chref' => ($cat !== '') ? getSeoUrl(['name' => $type->name, 'cat' => $node->cid]) : '',
            'date' => ($node->pubdate !== null) ? format_time($node->pubdate) : '',
            'date_iso' => $this->getIsoDate($node->pubdate),
            'mtime_iso' => $this->getIsoDate($node->updated),
            'views' => $node->views,
            'comnum' => $node->comnum,
            'rating' => $this->getAverage($node->score, $node->ratings),
            'ratings' => $node->ratings,
            'fields' => ($node->fields !== null) ? $this->fld->getFieldView($this->prs, $type->fields, $node->fields, $type->name) : [],
            'assets' => ($node->assets !== null) ? $this->getAssetView($type, $node->assets) : [],
        ];
    }

    # The array of a light target: the same keys, with everything a target does not carry left empty and the counters it does carry filled
    private function getTargetView(NodeType $type, NodeTarget $node, string $mode): array {
        return [
            'id' => $node->id,
            'type' => $type->name,
            'mode' => $mode,
            'href' => $this->getViewHref($type, $node->id, $node->title),
            'title' => $node->title,
            'intro' => '',
            'intro_html' => '',
            'body_html' => '',
            'author' => '',
            'ahref' => '',
            'ctitle' => '',
            'chref' => '',
            'date' => '',
            'date_iso' => '',
            'mtime_iso' => '',
            'views' => null,
            'comnum' => $node->comnum,
            'rating' => $this->getAverage($node->score, $node->ratings),
            'ratings' => $node->ratings,
            'fields' => [],
            'assets' => [],
        ];
    }
}
