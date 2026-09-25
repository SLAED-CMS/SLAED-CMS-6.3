<?php

declare(strict_types=1);

namespace Tests\Unit;

use Field;
use Parser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

require_once dirname(__DIR__, 2).'/core/classes/field.php';
require_once dirname(__DIR__, 2).'/core/classes/template.php';
require_once dirname(__DIR__, 2).'/core/classes/parser.php';

/**
 * Stage S06 of docs/node: the two prepared outputs of the shared Field class and the wiring of its three owners. The
 * form is built from the shared fragments without reading POST, the view is the exact contract array of
 * docs/node/05-core-api.md, and account, forum and order reach their values through the shared helpers alone - no
 * positional format, no second reader and no writer that skips the mark of the 6.3 data update.
 */
final class FieldViewTest extends TestCase
{
    # The definitions every test here shares: a required text with a hint, a multiple select with a disabled option, a switch, a textarea, an address, a mail and a hidden field
    private static function getSet(): array
    {
        $rule = ['title' => 'T', 'intro' => '', 'type' => 'text', 'default' => '', 'options' => [], 'req' => false, 'multi' => false, 'active' => true, 'sort' => 10];
        $items = [
            'pdf' => ['title' => 'PDF', 'active' => true, 'sort' => 20],
            'doc' => ['title' => 'DOC', 'active' => true, 'sort' => 10],
            'old' => ['title' => 'Old', 'active' => false, 'sort' => 30],
        ];
        return [
            'name' => ['title' => 'Name', 'intro' => 'Your "name" & more', 'req' => true, 'sort' => 1] + $rule,
            'kind' => ['title' => 'Kind', 'type' => 'select', 'multi' => true, 'default' => [], 'options' => ['items' => $items], 'sort' => 2] + $rule,
            'flag' => ['title' => 'Flag', 'type' => 'bool', 'default' => null, 'sort' => 3] + $rule,
            'note' => ['title' => 'Note', 'type' => 'textarea', 'sort' => 4] + $rule,
            'site' => ['title' => 'Site', 'type' => 'url', 'sort' => 5] + $rule,
            'mail' => ['title' => 'Mail', 'type' => 'email', 'sort' => 6] + $rule,
            'when' => ['title' => 'When', 'type' => 'datetime', 'sort' => 7] + $rule,
            'gone' => ['title' => 'Gone', 'active' => false, 'sort' => 8] + $rule,
        ];
    }

    # A template double that answers the fragment name and its data instead of markup, so the test reads what the class hands to the theme
    private static function getTemplate(): Template
    {
        return new class () extends Template {
            public function __construct()
            {
            }

            public function getHtmlFrag(string $name, array $data = []): string
            {
                return '['.$name.':'.json_encode($data, JSON_UNESCAPED_UNICODE).']';
            }
        };
    }

    # A parser double that marks its output and records the safe flag it was called with
    private static function getParser(): Parser
    {
        return new class () extends Parser {
            public function filterContent(string $src, bool $safe, string $mod, int $hoff = 0, string $fmt = '', int $nid = 0, bool $trust = false): string
            {
                return '<p data-safe="'.(int)$safe.'" data-mod="'.$mod.'">'.htmlspecialchars($src).'</p>';
            }
        };
    }

    public static function setUpBeforeClass(): void
    {
        foreach (['_YES' => 'Yes', '_NO' => 'No', '_FIELDS_REQ' => 'Fill it in.', '_FIELDS_FORMAT' => 'Wrong format.'] as $name => $text) {
            if (!defined($name)) define($name, $text);
        }
    }

    # The form has one row per active field in canonical order, keyed by name, with the control of its type, the hint tied to the control and the message of a refused value
    #[Test]
    public function theFormIsBuiltFromTheSharedFragments(): void
    {
        $rows = (new Field())->getFieldForm(
            self::getTemplate(),
            self::getSet(),
            ['name' => 'Ann', 'kind' => ['pdf'], 'flag' => true, 'when' => '2026-03-01 09:05:00'],
            ['site' => 'format', 'mail' => 'nonsense']
        );
        $this->assertSame(['name', 'kind', 'flag', 'note', 'site', 'mail', 'when'], array_keys($rows), 'A hidden field got a row or the order is not canonical');
        $keys = ['name', 'type', 'label_for', 'label_text', 'hint_id', 'hint_text', 'error_text', 'is_required', 'field_html'];
        foreach ($rows as $row) $this->assertSame($keys, array_keys($row));
        $this->assertSame(
            ['f-field-name', 'Name', 'f-field-name-hint', 'Your "name" & more', '', true],
            array_slice(array_values($rows['name']), 2, 6),
            'The hint is plain text and is never escaped by the class'
        );
        $this->assertStringContainsString('"name_attr":"field[name]"', $rows['name']['field_html']);
        $this->assertStringContainsString('"describedby":"f-field-name-hint"', $rows['name']['field_html']);
        $this->assertStringContainsString('"value_attr":"Ann"', $rows['name']['field_html']);
        $this->assertStringContainsString('"maxlength_num":4096', $rows['name']['field_html']);
        $this->assertSame(
            ['Wrong format.', 'f-field-site-hint', ''],
            [$rows['site']['error_text'], $rows['site']['hint_id'], $rows['mail']['error_text']],
            'A known code gets its message and an unknown one none'
        );
        $kind = $rows['kind']['field_html'];
        $this->assertStringStartsWith('[select:', $kind);
        $this->assertStringContainsString('"is_multiple":true', $kind);
        $this->assertStringContainsString('"is_name_array":true', $kind);
        $this->assertSame(2, substr_count($kind, 'select-option'), 'A multiple select offers its two active options and no empty one');
        $this->assertStringNotContainsString('Old', $kind, 'A disabled option is offered for a new choice');
        $this->assertMatchesRegularExpression('/select-option:\{\\\\"value_attr\\\\":\\\\"pdf\\\\",\\\\"label_text\\\\":\\\\"PDF\\\\",\\\\"is_selected\\\\":true/', $kind);
        $flag = $rows['flag']['field_html'];
        $this->assertStringStartsWith('[hidden:{"name_attr":"field[flag]","value_attr":"0"}][checkbox:', $flag, 'An unchecked switch would post nothing');
        $this->assertStringContainsString('"is_checked":true', $flag);
        $this->assertStringStartsWith('[textarea:', $rows['note']['field_html']);
        $this->assertStringContainsString(
            '"itype":"datetime-local","value_attr":"2026-03-01T09:05:00"',
            $rows['when']['field_html'],
            'A stored moment is shown in the form its input expects'
        );
        $this->assertStringContainsString('"itype":"url"', $rows['site']['field_html']);
        $this->assertStringContainsString('"itype":"email"', $rows['mail']['field_html']);
    }

    # A single select offers an empty choice first, and a decimal input steps by its scale
    #[Test]
    public function aSingleSelectAndADecimalGetTheirControls(): void
    {
        $set = self::getSet();
        $set = [
            'kind' => ['multi' => false, 'default' => '', 'req' => true] + $set['kind'],
            'price' => ['title' => 'Price', 'type' => 'decimal', 'options' => ['scale' => 3]] + $set['name'],
        ];
        $rows = (new Field())->getFieldForm(self::getTemplate(), $set, []);
        $this->assertSame(3, substr_count($rows['kind']['field_html'], 'select-option'));
        $this->assertMatchesRegularExpression(
            '/^\[select:\{.*"options_html":"\[select-option:\{\\\\"value_attr\\\\":\\\\"\\\\",\\\\"label_text\\\\":\\\\"No\\\\",\\\\"is_selected\\\\":true/',
            $rows['kind']['field_html']
        );
        $this->assertStringContainsString('"select_attr":"required"', $rows['kind']['field_html']);
        $this->assertStringContainsString('"itype":"number"', $rows['price']['field_html']);
        $this->assertStringContainsString('step=\"0.001\"', $rows['price']['field_html']);
    }

    # The view is the contract array: exact keys, canonical order, false and zero shown, empty and hidden left out, a stored disabled option kept, a removed one dropped
    #[Test]
    public function theViewIsTheContractArray(): void
    {
        $vals = [
            'name' => '0', 'kind' => ['old', 'zip', 'doc'], 'flag' => false, 'note' => "<b>x</b>\nline", 'site' => 'https://Example.com/A?b=C',
            'mail' => 'a@b.de', 'when' => '', 'gone' => 'kept', 'ghost' => 'x',
        ];
        $view = (new Field())->getFieldView(self::getParser(), self::getSet(), $vals, 'forum');
        $this->assertSame(['name', 'kind', 'flag', 'note', 'site', 'mail'], array_keys($view));
        foreach ($view as $name => $row) {
            $this->assertSame(['name', 'type', 'label_text', 'hint_text', 'value', 'value_text', 'value_html', 'value_href', 'items'], array_keys($row));
            $this->assertSame($name, $row['name'], 'The name is repeated inside for a template that walks the list');
        }
        $this->assertSame(['0', '0', '', ''], [$view['name']['value'], $view['name']['value_text'], $view['name']['value_html'], $view['name']['value_href']]);
        $this->assertSame([false, 'No'], [$view['flag']['value'], $view['flag']['value_text']]);
        $this->assertSame(['doc', 'old'], $view['kind']['value'], 'A stored disabled option stays, a removed one goes, the order is that of the definition');
        $this->assertSame([['value' => 'doc', 'label_text' => 'DOC'], ['value' => 'old', 'label_text' => 'Old']], $view['kind']['items']);
        $this->assertSame('DOC, Old', $view['kind']['value_text']);
        $this->assertSame(
            '<p data-safe="1" data-mod="forum">&lt;b&gt;x&lt;/b&gt;'."\n".'line</p>',
            $view['note']['value_html'],
            'Only a textarea gets parser output, and the parser runs in its safe mode'
        );
        $this->assertSame(['https://Example.com/A?b=C', 'mailto:a@b.de'], [$view['site']['value_href'], $view['mail']['value_href']]);
        $this->assertSame([], $view['site']['items']);
    }

    # A stored value that no longer fits its type is left out of the view instead of being shown repaired, while a tightened limit does not hide what was valid when it was saved
    #[Test]
    public function aBrokenStoredValueIsLeftOut(): void
    {
        $set = ['site' => self::getSet()['site'], 'name' => ['options' => ['max' => 3]] + self::getSet()['name']];
        $view = (new Field())->getFieldView(self::getParser(), $set, ['site' => 'javascript:alert(1)', 'name' => 'longer'], 'order');
        $this->assertSame(['name'], array_keys($view));
    }

    # The write helper strips the trusted tags however they are spelled, keeps the value of a switched off field against a forged post and drops a stored name without a definition;
    # a refused value, a refused set and a missing mark each leave the stored text as it was, and the forum no longer hands rendered rows to a trusted parse
    #[Test]
    public function theWriteHelperStoresNoCapabilityAndLosesNoValue(): void
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' fieldpost 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe fieldpost did not return JSON: '.$out);
        $old = '{"note":"before","off":"kept","ghost":"gone"}';
        $this->assertSame(['json' => '{"note":"echo 6*7; [b]x[/b]","area":"<script>1</script>\nline","off":"kept"}', 'errors' => [], 'stop' => []], $data['saved']);
        $this->assertSame([$old, ['note' => 'max']], [$data['refused']['json'], $data['refused']['errors']], 'A refused value changed the stored text');
        $this->assertCount(1, $data['refused']['stop']);
        $this->assertSame(['json' => $old, 'errors' => [], 'stop' => []], $data['broken'], 'A set that failed the shared check wiped the stored values');
        $this->assertSame(['json' => $old, 'errors' => [], 'stop' => []], $data['nomark'], 'A save without the mark of the data update rewrote the stored text');
        $this->assertSame([true, false], $data['view'], 'A stored tag was executed or silently altered on the way to the page');
        $forum = (string)file_get_contents(dirname(__DIR__, 2).'/modules/forum/index.php');
        $this->assertSame(0, preg_match('/filterContent\([^;]*\$fields/', $forum), 'The forum hands the rendered field rows to the parser again');
    }
    # The three owners reach their fields through the shared helpers alone: no positional split, no positional input name, no second reader of the definitions
    #[Test]
    public function theOwnersUseTheSharedHelpersAlone(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['admin', 'blocks', 'core', 'modules', 'plugins'] as $dir) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $file) {
                if ($file->getExtension() !== 'php') continue;
                $code = (string)file_get_contents($file->getPathname());
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $owner = (bool)preg_match('#^(?:core/helpers\.php|admin/modules/fields\.php|modules/(?:account|forum|order)/)#', $path);
                if ($owner) $this->assertStringNotContainsString("explode('||'", $code, $path.' still splits positional definitions');
                $this->assertStringNotContainsString("'field[]'", str_replace("getVar('post', 'field[]', '', [])", '', $code), $path.' still names a positional control');
                $this->assertSame(
                    0,
                    preg_match('/getVar\([^)]*,\s*\'field\'\s*[,)]/', str_replace("getVar('post', 'field', 'raw'", '', $code)),
                    $path.' still reads the positional input filter'
                );
                if (!in_array($path, ['core/helpers.php', 'admin/modules/fields.php'], true)) {
                    $this->assertStringNotContainsString("\$conf['fields']", $code, $path.' reads the definitions past getFieldRules()');
                }
            }
        }
        $help = (string)file_get_contents($root.'/core/helpers.php');
        $this->assertSame(1, preg_match_all('/new Field\(/', (string)file_get_contents($root.'/core/system.php')), 'The shared instance is built in more than one place');
        $this->assertSame(
            2,
            substr_count($help, "(\$conf['update']['fields'] ?? '') === '6.3.0'") + substr_count($help, "(\$conf['update']['fields'] ?? '') !== '6.3.0'"),
            'A helper reads or writes fields past the mark of the data update'
        );
        foreach ([
            'modules/order/index.php' => 1, 'modules/order/admin/index.php' => 2, 'modules/forum/index.php' => 1,
            'modules/account/index.php' => 1, 'modules/account/admin/index.php' => 1,
        ] as $path => $num) {
            $this->assertSame($num, substr_count((string)file_get_contents($root.'/'.$path), 'getFieldsPost('), $path.' does not write its fields through getFieldsPost()');
        }
        $this->assertStringNotContainsString('filterFields(trim(', (string)file_get_contents($root.'/core/security.php'), 'getVar() still carries the positional input filter');
    }
}
