<?php

declare(strict_types=1);

namespace {
    if (!function_exists('getCookies')) {
        function getCookies(string $name): string
        {
            return '';
        }
    }
    if (!function_exists('getSiteToken')) {
        function getSiteToken(string $scope = 'ajax'): string
        {
            return 'token-'.$scope;
        }
    }
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use Template;

    # The row of the list canon by meaning: what getTplAdminList() builds from moment, details, is_edit, is_find, drop and stat, and what it leaves alone without them
    final class AdminListRowTest extends TestCase
    {
        private static mixed $was = null;

        public static function setUpBeforeClass(): void
        {
            if (!class_exists('Template', false)) require_once dirname(__DIR__, 2).'/core/classes/template.php';
            if (!function_exists('getTplAdminList')) require_once dirname(__DIR__, 2).'/core/helpers.php';
            $text = [
                '_BACK' => 'Back', '_NEXT' => 'Next', '_OVERALL' => 'In total', '_PAGE' => 'Page', '_NUMOF' => '%1$s of %2$s', '_PAGERANGE' => 'Showing %1$s–%2$s',
                '_NO_INFO' => 'No info', '_ADM_STD' => 'Standard', '_ADM_FULL' => 'Full', '_ADM_DENSE' => 'Dense', '_ADM_CARDS' => 'Cards', '_ADM_PICKED' => 'Picked: %s',
                '_ADM_ALLN' => 'Pick all %s', '_CHECKALL' => 'Check all', '_DETAILS' => 'Details', '_TIMESTRING' => 'd.m.Y H:i', '_ACTIVATE' => 'Activate',
                '_DEACTIVATE' => 'Deactivate', '_DELETE' => 'Delete',
            ];
            foreach ($text as $name => $val) {
                if (!defined($name)) define($name, $val);
            }
            self::$was = $GLOBALS['tpl'] ?? null;
            $GLOBALS['tpl'] = new Template('admin');
            $GLOBALS['afile'] = 'admin';
        }

        public static function tearDownAfterClass(): void
        {
            $GLOBALS['tpl'] = self::$was;
        }

        # Render one list of the canon with the given head and rows and answer it as a document
        private static function getList(array $head, array $rows, array $more = []): \Dom\HTMLDocument
        {
            $html = getTplAdminList(['head' => $head, 'rows' => $rows, 'key' => 'probe', 'href' => 'admin.php?name=probe&', 'count' => count($rows), 'limit' => 25] + $more);
            return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
        }

        # One row with a title cell and nothing else, so each test adds only the key it reads
        private static function getRow(array $keys = [], array $cells = []): array
        {
            return ['name' => 'Row 7', 'cells' => $cells ?: [['is_col_id' => true, 'content_html' => '7'], ['is_truncate' => true, 'text_html' => 'Title']]] + $keys;
        }

        # The tags of a markup in order, each with the sorted names of its attributes: its composition without the values that legitimately move
        private static function getShape(string $html): array
        {
            preg_match_all('#<(/?[a-z]+)((?:\s+[a-z:-]+(?:="[^"]*"|=\'[^\']*\')?)*)\s*/?>#i', $html, $mats, PREG_SET_ORDER);
            $out = [];
            foreach ($mats as $mat) {
                preg_match_all('#\s([a-z:-]+)(?==|\s|$)#i', $mat[2].' ', $names);
                $keys = $names[1];
                sort($keys);
                $out[] = $mat[1].($keys ? '['.implode(' ', $keys).']' : '');
            }
            return $out;
        }

        #[Test]
        public function momentDrawsTimeWithSortKey(): void
        {
            $head = [['content' => 'Date', 'is_col_date' => true]];
            $when = '2026-10-23 14:05:00';
            $doc = self::getList($head, [
                ['name' => 'A', 'cells' => [['is_col_date' => true, 'moment' => $when]]],
                ['name' => 'B', 'cells' => [['is_col_date' => true, 'moment' => strtotime($when)]]],
                ['name' => 'C', 'cells' => [['is_col_date' => true, 'moment' => null]]],
                ['name' => 'D', 'cells' => [['is_col_date' => true, 'content_html' => 'plain']]],
            ]);
            $cells = $doc->querySelectorAll('tbody td');
            foreach ([0, 1] as $at) {
                $time = $cells[$at]->querySelector('time[data-sl-ago]');
                $this->assertNotNull($time, 'A moment drew no time in row '.$at);
                $this->assertSame(date('c', strtotime($when)), $time->getAttribute('datetime'));
                $this->assertSame(date('d.m.Y H:i', strtotime($when)), $time->textContent);
                $this->assertSame(date('c', strtotime($when)), $cells[$at]->getAttribute('data-sort'), 'A moment lost its sort key');
            }
            $this->assertNull($cells[2]->querySelector('time'), 'An empty moment drew a time');
            $this->assertSame('', trim($cells[2]->textContent), 'An empty moment printed text');
            $this->assertNull($cells[3]->querySelector('time'), 'A cell without a moment drew a time');
            $this->assertSame('plain', trim($cells[3]->textContent));
        }

        #[Test]
        public function anEmptyMomentKeepsTheTextOfItsModule(): void
        {
            $head = [['content' => 'Next run', 'is_col_date' => true]];
            $when = '2026-10-23 14:05:00';
            $doc = self::getList($head, [
                ['name' => 'A', 'cells' => [['is_col_date' => true, 'moment' => 0, 'has_content_text' => true, 'content_text' => 'No <end>']]],
                ['name' => 'B', 'cells' => [['is_col_date' => true, 'moment' => $when, 'has_content_text' => true, 'content_text' => 'No <end>']]],
            ]);
            $cells = $doc->querySelectorAll('tbody td');
            $this->assertNull($cells[0]->querySelector('time'), 'An empty moment drew a time');
            $this->assertSame('No <end>', trim($cells[0]->textContent), 'An empty moment lost the text of its module');
            $this->assertFalse($cells[0]->hasAttribute('data-sort'));
            $time = $cells[1]->querySelector('time[data-sl-ago]');
            $this->assertNotNull($time, 'A moment with a fallback text drew no time');
            $this->assertSame(date('d.m.Y H:i', strtotime($when)), $time->textContent, 'The fallback text of a module beat its moment');
        }

        #[Test]
        public function detailsStandInTheFirstCellPastTheId(): void
        {
            $head = [['content' => 'ID', 'is_col_id' => true], ['content' => 'Title', 'is_truncate' => true]];
            $doc = self::getList($head, [self::getRow(['details' => [['label' => 'IP', 'value' => '<b>10.0.0.1</b>']]]), self::getRow()]);
            $rows = $doc->querySelectorAll('tbody tr');
            $this->assertNull($rows[0]->querySelector('td.sl-col-id .sl-tip'), 'The details went into the id cell');
            $cell = $rows[0]->querySelectorAll('td')[1];
            $tip = $cell->querySelector('.sl-float.sl-tip');
            $this->assertNotNull($tip, 'The details drew no "i"');
            $this->assertSame('Details: Row 7', $tip->querySelector('button')->getAttribute('aria-label'));
            $this->assertSame('sl-tip-1', $tip->querySelector('[role="tooltip"]')->getAttribute('id'));
            $this->assertSame('IP', $tip->querySelector('dt')->textContent);
            $this->assertNotNull($tip->querySelector('dd b'), 'A value of the details lost its markup');
            $this->assertSame('sl-float sl-tip', $cell->firstElementChild->getAttribute('class'), 'The "i" does not lead its cell');
            $this->assertNull($rows[1]->querySelector('.sl-tip'), 'A row without details grew an "i"');
        }

        #[Test]
        public function dialItemsCarryEditAndFind(): void
        {
            $head = [['content' => 'Functions', 'is_col_actions' => true]];
            $dial = [
                ['href' => 'admin.php?op=edit', 'icon_name' => 'pencil', 'title' => 'Edit', 'is_edit' => true],
                ['href' => 'admin.php?find=1', 'icon_name' => 'search', 'title' => 'Find', 'is_find' => true],
                ['href' => 'index.php', 'icon_name' => 'eye', 'title' => 'View'],
            ];
            $doc = self::getList($head, [['name' => 'A', 'cells' => [['is_col_actions' => true, 'dial' => $dial]]]]);
            $links = $doc->querySelectorAll('tbody .sl-dial a.sl-dial-item');
            $this->assertCount(3, $links);
            $this->assertSame('Functions', $doc->querySelector('tbody .sl-dial-toggle')->getAttribute('title'), 'The dial is not named by its column');
            $this->assertTrue($links[0]->hasAttribute('data-sl-edit'), 'The edit item does not answer the key E');
            $this->assertFalse($links[0]->hasAttribute('hx-boost'));
            $this->assertSame('true', $links[1]->getAttribute('hx-boost'), 'The find item does not swap the list');
            $this->assertFalse($links[1]->hasAttribute('data-sl-edit'));
            $this->assertFalse($links[2]->hasAttribute('data-sl-edit'), 'A plain item answers the key E');
            $this->assertFalse($links[2]->hasAttribute('hx-boost'), 'A plain item swaps the list');
        }

        #[Test]
        public function dropBringsTheBoxTheDialItemAndTheFoot(): void
        {
            $head = [['content' => 'ID', 'is_col_id' => true], ['content' => 'Title', 'is_truncate' => true], ['content' => 'Functions', 'is_col_actions' => true]];
            $cells = static fn(int $id): array => [['is_col_id' => true, 'content_html' => (string)$id], ['is_truncate' => true, 'text_html' => 'T'],
                ['is_col_actions' => true, 'dial' => [['href' => 'index.php', 'icon_name' => 'eye', 'title' => 'View']]]];
            $drop = static fn(int $id): array => getTplPostAction(['name' => 'probe', 'num' => 2, 'op' => 'delete', 'id[]' => $id], 'trash', 'Delete');
            $doc = self::getList($head, [
                ['name' => 'Row 1', 'drop' => $drop(1), 'cells' => $cells(1)],
                ['name' => 'Row 2', 'cells' => $cells(2)],
            ]);
            $this->assertNotNull($doc->querySelector('thead th.sl-col-check[data-sl-check-all] input#markcheck[form="sl-list-pick"]'), 'The head lost its box');
            $rows = $doc->querySelectorAll('tbody tr');
            $box = $rows[0]->querySelector('td.sl-col-check input[name="id[]"][data-sl-check-item]');
            $this->assertNotNull($box, 'A row with drop has no box');
            $this->assertSame('1', $box->getAttribute('value'));
            $this->assertSame('Row 1', $box->getAttribute('aria-label'), 'The box is not named by its row');
            $this->assertSame('sl-list-pick', $box->getAttribute('form'));
            $this->assertSame('td', $rows[0]->lastElementChild->localName);
            $this->assertTrue($rows[0]->lastElementChild->classList->contains('sl-col-check'), 'The box is not the last cell');
            $items = $rows[0]->querySelectorAll('.sl-dial > .sl-dial-item');
            $this->assertCount(2, $items);
            $this->assertSame('submit', $items[1]->getAttribute('type'), 'The delete is not the last item of the dial');
            $post = $doc->getElementById($items[1]->getAttribute('form'));
            $this->assertSame('gone del', $post->getAttribute('data-sl-later'), 'The delete of a row is not deferred');
            $this->assertNotNull($rows[1]->querySelector('td.sl-col-check'), 'A row without drop lost its place in the box column');
            $this->assertNull($rows[1]->querySelector('input[type="checkbox"]'), 'A row without drop grew a box');
            $this->assertCount(1, $rows[1]->querySelectorAll('.sl-dial-item'), 'A row without drop grew a delete');
            $foot = $doc->getElementById('sl-list-pick');
            $this->assertSame('/admin.php', $foot->getAttribute('action'));
            $this->assertSame('gone del=del', $foot->getAttribute('data-sl-later'));
            $sets = $foot->querySelectorAll('fieldset[data-sl-pick]');
            $this->assertCount(1, $sets);
            $this->assertFalse($sets[0]->hasAttribute('disabled'), 'The first group of the foot is not the one sent');
            $want = ['name' => 'probe', 'num' => '2', 'op' => 'delete', 'token' => getSiteToken('probe')];
            $this->assertSame($want, self::getFields($sets[0]), 'The delete group is not the row action without its id');
            $opts = iterator_to_array($foot->querySelectorAll('select[name="pick"] option'));
            $this->assertSame(['del'], array_map(static fn($opt): string => $opt->getAttribute('value'), $opts));
        }

        #[Test]
        public function withoutDropTheListHasNoBoxes(): void
        {
            $head = [['content' => 'ID', 'is_col_id' => true], ['content' => 'Title', 'is_truncate' => true]];
            $doc = self::getList($head, [self::getRow()]);
            $this->assertNull($doc->querySelector('.sl-col-check'), 'A list without drop grew a box column');
            $this->assertNull($doc->getElementById('sl-list-pick'), 'A list without drop grew a foot');
            $this->assertNull($doc->querySelector('[data-sl-picked]'));
        }

        #[Test]
        public function statThatLeavesJoinsTheFootByDirection(): void
        {
            $head = [['content' => 'Status', 'is_col_status' => true]];
            $stat = static fn(int $id, bool $on, string $later): array => getTplPostAction(['name' => 'probe', 'op' => 'approve', 'id[]' => $id, 'typ' => $on ? 0 : 1],
                'power', 'Turn') + ['is_on' => $on, 'label' => 'Status', 'later' => $later];
            $drop = static fn(int $id): array => getTplPostAction(['name' => 'probe', 'op' => 'delete', 'id[]' => $id], 'trash', 'Delete');
            $doc = self::getList($head, [
                ['name' => 'On', 'drop' => $drop(1), 'cells' => [['is_col_status' => true, 'stat' => $stat(1, true, 'gone off')]]],
                ['name' => 'Off', 'drop' => $drop(2), 'cells' => [['is_col_status' => true, 'stat' => $stat(2, false, 'gone on')]]],
            ]);
            $foot = $doc->getElementById('sl-list-pick');
            $this->assertSame('gone on=on off=off del=del', $foot->getAttribute('data-sl-later'));
            $opts = iterator_to_array($foot->querySelectorAll('select[name="pick"] option'));
            $this->assertSame(['on', 'off', 'del'], array_map(static fn($opt): string => $opt->getAttribute('value'), $opts));
            $this->assertSame(['Activate', 'Deactivate', 'Delete'], array_map(static fn($opt): string => $opt->textContent, $opts));
            $sets = iterator_to_array($foot->querySelectorAll('fieldset[data-sl-pick]'));
            $this->assertSame(['on', 'off', 'del'], array_map(static fn($set): string => $set->getAttribute('data-sl-pick'), $sets));
            $this->assertSame([false, true, true], array_map(static fn($set): bool => $set->hasAttribute('disabled'), $sets), 'More than the first group is sent');
            $tok = getSiteToken('probe');
            $this->assertSame(['name' => 'probe', 'op' => 'approve', 'typ' => '1', 'token' => $tok], self::getFields($sets[0]), 'Turning on is not the form of a row that is off');
            $this->assertSame(['name' => 'probe', 'op' => 'approve', 'typ' => '0', 'token' => $tok], self::getFields($sets[1]), 'Turning off is not the form of a row that is on');
            $lamp = self::getList($head, [['name' => 'On', 'drop' => $drop(1), 'cells' => [['is_col_status' => true, 'stat' => $stat(1, true, 'lamp typ')]]]]);
            $this->assertSame('gone off=off del=del', $lamp->getElementById('sl-list-pick')->getAttribute('data-sl-later'), 'A lamp that stays did not join the foot');
            $this->assertSame(['name' => 'probe', 'op' => 'approve', 'typ' => '0', 'token' => $tok], self::getFields($lamp->querySelector('fieldset[data-sl-pick="off"]')));
            $bare = self::getList($head, [['name' => 'On', 'cells' => [['is_col_status' => true, 'stat' => $stat(1, true, 'gone off')]]]]);
            $this->assertNull($bare->getElementById('sl-list-pick'), 'A status without drop grew a foot');
        }

        # A drop that moves its row elsewhere stays deferred as a move, and the foot names its action by the title of the drop
        #[Test]
        public function aDropThatMovesNamesItsFootAction(): void
        {
            $head = [['content' => 'Title', 'is_truncate' => true], ['content' => 'Functions', 'is_col_actions' => true]];
            $drop = getTplPostAction(['name' => 'probe', 'op' => 'revive', 'kind' => 'ip', 'id[]' => 4], 'arrow-counterclockwise', 'Send again') + ['later' => 'gone move'];
            $doc = self::getList($head, [['name' => 'A', 'drop' => $drop, 'cells' => [['text_html' => 'A'], ['is_col_actions' => true, 'dial' => []]]]]);
            $item = $doc->querySelector('tbody .sl-dial-item[type="submit"]');
            $this->assertSame('gone move', $doc->getElementById($item->getAttribute('form'))->getAttribute('data-sl-later'), 'The move of a row became a delete');
            $foot = $doc->getElementById('sl-list-pick');
            $this->assertSame('gone move=move', $foot->getAttribute('data-sl-later'));
            $opt = $foot->querySelector('select[name="pick"] option');
            $this->assertSame(['move', 'Send again'], [$opt->getAttribute('value'), $opt->textContent]);
            $set = $foot->querySelector('fieldset[data-sl-pick="move"]');
            $this->assertSame(['name' => 'probe', 'op' => 'revive', 'kind' => 'ip', 'token' => getSiteToken('probe')], self::getFields($set),
                'The foot dropped a field of the row action other than id[]');
            $this->assertSame('4', $doc->querySelector('tbody input[name="id[]"]')->getAttribute('value'));
        }

        #[Test]
        public function theSweepRidesOnTheFootOverMorePages(): void
        {
            $head = [['content' => 'Title', 'is_truncate' => true]];
            $row = ['name' => 'A', 'drop' => getTplPostAction(['name' => 'probe', 'op' => 'delete', 'id[]' => 1], 'trash', 'Delete'), 'cells' => [['text_html' => 'A']]];
            $doc = self::getList($head, [$row], ['pages' => 3, 'count' => 60, 'upto' => 99]);
            $note = $doc->querySelector('[data-sl-allpages]');
            $this->assertNotNull($note, 'A list of more pages lost "Pick all N"');
            $this->assertSame('99', $note->getAttribute('data-sl-allpages-upto'));
            $this->assertSame('delete', $doc->querySelector('fieldset[data-sl-pick="del"] input[name="op"]')->getAttribute('value'), 'The sweep lost the op of its foot action');
            $this->assertNull(self::getList($head, [$row], ['upto' => 99])->querySelector('[data-sl-allpages]'), 'One page offered all pages');
            $this->assertNull(self::getList($head, [$row], ['pages' => 3, 'count' => 60])->querySelector('[data-sl-allpages]'), 'A list without a bound offered all pages');
        }

        # The row of the comments drawn from its keys holds the same tags and attributes in the same order as the row the module glued together itself
        #[Test]
        public function theCommentRowKeepsItsComposition(): void
        {
            global $tpl;
            $head = [['content' => 'ID', 'is_col_id' => true], ['content' => 'Comment', 'is_truncate' => true], ['content' => 'Date', 'is_col_date' => true],
                ['content' => 'Status', 'is_col_status' => true, 'nosort' => true], ['content' => 'Functions', 'is_col_actions' => true, 'nosort' => true]];
            $when = '2026-10-23 14:05:00';
            $keep = ['name' => 'comments', 'search' => 2];
            $tips = [['label' => 'Module', 'value' => 'News - news'], ['label' => 'IP', 'value' => '10.0.0.1']];
            $view = ['href' => 'index.php?name=news', 'icon_name' => 'eye', 'title' => 'View'];
            $stat = getTplPostAction($keep + ['op' => 'approve', 'id[]' => 5, 'typ' => 0], 'power', 'Deactivate')
                + ['is_on' => true, 'label' => 'Status: Comment 5', 'later' => 'gone off'];
            $drop = getTplPostAction($keep + ['op' => 'delete', 'id[]' => 5], 'trash', 'Delete');
            $glued = ['name' => 'Comment 5', 'cells' => [
                ['is_col_id' => true, 'content_html' => '5'],
                ['is_truncate' => true, 'title_text' => 'Body', 'text_html' => 'Body',
                    'prefix_html' => $tpl->getHtmlFrag('popover', ['items' => $tips, 'tip_label' => 'Details: Comment 5', 'tip_id' => 'sl-tip-5'])],
                ['is_col_date' => true, 'stamp' => date('c', strtotime($when)), 'content_text' => date('d.m.Y H:i', strtotime($when))],
                ['is_col_status' => true, 'stat' => $stat],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => 'Functions', 'dial' => [$view,
                    ['href' => 'admin.php?op=edit', 'icon_name' => 'pencil', 'title' => 'Edit', 'link_attr' => 'data-sl-edit'],
                    ['href' => 'admin.php?search=5', 'icon_name' => 'hdd-network', 'title' => 'IP', 'link_attr' => 'hx-boost="true"'],
                    $drop + ['later' => 'gone del']]])],
                ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => '5', 'is_check' => true, 'aria_label' => 'Pick 5',
                    'input_attr' => 'form="combulk"'])],
            ]];
            $meant = ['name' => 'Comment 5', 'details' => $tips, 'drop' => $drop, 'cells' => [
                ['is_col_id' => true, 'content_html' => '5'],
                ['is_truncate' => true, 'title_text' => 'Body', 'text_html' => 'Body'],
                ['is_col_date' => true, 'moment' => $when],
                ['is_col_status' => true, 'stat' => $stat],
                ['is_col_actions' => true, 'dial' => [$view, ['href' => 'admin.php?op=edit', 'icon_name' => 'pencil', 'title' => 'Edit', 'is_edit' => true],
                    ['href' => 'admin.php?search=5', 'icon_name' => 'hdd-network', 'title' => 'IP', 'is_find' => true]]],
            ]];
            $mark = $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'input_id' => 'markcheck', 'is_check' => true, 'aria_label' => 'Check all',
                'input_attr' => 'title="Check all" form="combulk"']);
            $old = self::getList([...$head, ['is_col_check' => true, 'nosort' => true, 'content' => $mark]], [$glued]);
            $new = self::getList($head, [$meant]);
            $shape = static fn(\Dom\HTMLDocument $doc, string $tag): array => self::getShape($doc->saveHtml($doc->querySelector($tag)));
            $this->assertGreaterThan(40, count($shape($new, 'tbody')), 'The shape of a row reads too few tags to compare');
            $this->assertSame($shape($old, 'thead'), $shape($new, 'thead'), 'The head of the comments changed');
            $this->assertSame($shape($old, 'tbody'), $shape($new, 'tbody'), 'The row of the comments changed');
            $this->assertSame($old->querySelector('tbody')->textContent, $new->querySelector('tbody')->textContent, 'The text of the comment row changed');
        }

        # The etalon hands the canon the meaning of its rows and glues none of the parts the canon now builds
        #[Test]
        public function theCommentsGlueNothingOfTheCanon(): void
        {
            $code = (string)file_get_contents(dirname(__DIR__, 2).'/admin/modules/comments.php');
            foreach (["'popover'", "'stamp'", 'data-sl-edit', 'hx-boost', "'checkbox'", 'markcheck', "'later' => 'gone del'", "case 'actions'"] as $part) {
                $this->assertStringNotContainsString($part, $code, 'The comments still glue '.$part);
            }
            foreach (["'details' =>", "'drop' =>", "'moment' =>", "'dial' =>", "'is_edit' => true", "'is_find' => true"] as $part) {
                $this->assertStringContainsString($part, $code, 'The comments do not hand the canon '.$part);
            }
        }

        # A function that renders a list of the canon hands it moments and details; the date and the "i" of a list are built by getTplAdminList() alone
        #[Test]
        public function noListGluesItsDateOrItsTip(): void
        {
            $root = dirname(__DIR__, 2);
            $files = [...glob($root.'/admin/modules/*.php'), ...glob($root.'/modules/*/admin/index.php')];
            $lists = [];
            foreach ($files as $file) {
                $code = (string)file_get_contents($file);
                $parts = preg_split('#^(?=function\s+\w+\s*\()#m', $code);
                foreach ($parts as $part) {
                    if (!str_contains($part, 'getTplAdminList(') || !preg_match('#^function\s+(\w+)#', $part, $mat)) continue;
                    $name = substr($file, strlen($root) + 1).' '.$mat[1].'()';
                    $lists[$name] = $part;
                    $this->assertStringNotContainsString("'popover'", $part, $name.' glues its own "i"');
                    $this->assertStringNotContainsString("'stamp'", $part, $name.' glues its own date');
                    foreach (preg_grep("#'is_col_date' => true#", explode("\n", $part)) as $line) {
                        if (!str_contains($line, "'content' =>")) $this->assertStringContainsString("'moment' =>", $line, $name.' has a date cell without a moment');
                    }
                }
            }
            $this->assertGreaterThanOrEqual(30, count($lists), 'The scan found too few lists to hold anything');
            foreach (['admin/modules/favorites.php favorites()', 'modules/voting/admin/index.php voting()'] as $name) {
                $this->assertArrayHasKey($name, $lists);
                $this->assertStringContainsString("'moment' =>", $lists[$name], $name.' has no date column');
            }
        }

        # The edit of every list row answers the key E: the pencil, the module file of the lang list (owner, 2026-10-23), the Node dial of core/system.php
        #[Test]
        public function everyListEditAnswersTheKeyE(): void
        {
            $root = dirname(__DIR__, 2);
            $files = [...glob($root.'/admin/modules/*.php'), ...glob($root.'/modules/*/admin/index.php'), $root.'/core/system.php'];
            $seen = 0;
            foreach ($files as $file) {
                $name = substr($file, strlen($root) + 1);
                $code = (string)file_get_contents($file);
                if ($name !== 'core/system.php') {
                    foreach (['data-sl-edit', 'hx-boost'] as $part) $this->assertStringNotContainsString($part, $code, $name.' glues '.$part.' instead of is_edit or is_find');
                }
                if ($name === 'admin/modules/groups.php') continue;
                foreach (self::getIconItems($code, $name === 'admin/modules/lang.php' ? 'puzzle' : 'pencil') as $item) {
                    $seen++;
                    $this->assertStringContainsString("'is_edit' =>", $item, $name.' has an edit the key E does not open: '.$item);
                }
            }
            $this->assertGreaterThanOrEqual(13, $seen, 'The scan found too few edits to hold anything');
        }

        # A list under a filter bar tells an empty result from an empty list, the queues of getAdminQueues() say they are clean (owner, 2026-10-24); the referers only sort
        #[Test]
        public function everyFilteredListTellsNothingFound(): void
        {
            $root = dirname(__DIR__, 2);
            $files = [...glob($root.'/admin/modules/*.php'), ...glob($root.'/modules/*/admin/index.php')];
            $lists = [];
            foreach ($files as $file) {
                $parts = [];
                foreach (preg_split('#^(?=function\s+\w+\s*\()#m', (string)file_get_contents($file)) as $part) {
                    if (preg_match('#^function\s+(\w+)#', $part, $mat)) $parts[$mat[1]] = $part;
                }
                $bars = array_keys(array_filter($parts, static fn($v) => str_contains($v, "'list-filter'")));
                foreach ($parts as $func => $part) {
                    if (!str_contains($part, 'getTplAdminList(')) continue;
                    $name = substr($file, strlen($root) + 1).' '.$func.'()';
                    $lists[$name] = $part;
                    $bar = str_contains($part, "'list-filter'") || array_filter($bars, static fn($v) => str_contains($part, $v.'('));
                    if (!$bar || $name === 'admin/modules/referers.php referers()') continue;
                    $this->assertMatchesRegularExpression("#'none_text' => .*_NONE_FOUND#", $part, $name.' says no info under its filter');
                }
            }
            $this->assertArrayHasKey('admin/modules/referers.php referers()', $lists);
            $queues = ['admin/modules/comments.php comments()', 'modules/account/admin/index.php newuser()', 'modules/node/admin/index.php show()',
                'modules/node/admin/index.php setNodeSupportQueue()'];
            foreach ($queues as $name) {
                $this->assertArrayHasKey($name, $lists);
                $this->assertStringContainsString('_ADM_CLEAN', $lists[$name], $name.' is a queue that does not say it is clean');
                $this->assertStringContainsString("'is_calm' =>", $lists[$name], $name.' is a queue whose empty state is not calm');
            }
        }

        # The array literal around every item of one icon in a file, read by tokens so a bracket inside a string never moves its bounds
        private static function getIconItems(string $code, string $icon): array
        {
            $toks = array_map(static fn($t) => is_array($t) ? $t[1] : $t, token_get_all($code));
            $out = [];
            foreach (array_keys($toks, "'".$icon."'", true) as $at) {
                for ($lo = $at, $deep = 0; $lo > 0; $lo--) {
                    if ($toks[$lo] === ']') $deep++;
                    if ($toks[$lo] === '[' && $deep-- === 0) break;
                }
                for ($hi = $at, $deep = 0; $hi < count($toks); $hi++) {
                    if ($toks[$hi] === '[') $deep++;
                    if ($toks[$hi] === ']' && $deep-- === 0) break;
                }
                $out[] = implode('', array_slice($toks, $lo, $hi - $lo + 1));
            }
            return $out;
        }

        # The hidden fields of one group of the foot as name and value
        private static function getFields(\Dom\Element $set): array
        {
            $out = [];
            foreach ($set->querySelectorAll('input[type="hidden"]') as $one) $out[$one->getAttribute('name')] = $one->getAttribute('value');
            return $out;
        }
    }
}
