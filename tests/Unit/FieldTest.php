<?php

declare(strict_types=1);

namespace Tests\Unit;

use Field;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

require_once dirname(__DIR__, 2).'/core/classes/field.php';

/**
 * Stage S06 of docs/node: the shared Field class. The closed registry of ten types, the atomic check of definitions with
 * the path of the first error, one normalization behind the check and the filter, the six machine codes, the hard
 * ceilings no option raises, and the shape of the class itself - six public methods, no constructor, no state.
 */
final class FieldTest extends TestCase
{
    # One valid definition; the overrides replace whole keys
    private static function getRule(array $over = []): array
    {
        return $over + ['title' => 'Title', 'intro' => '', 'type' => 'text', 'default' => '', 'options' => [], 'req' => false, 'multi' => false, 'active' => true, 'sort' => 10];
    }

    # One valid select definition over three options, the last one disabled
    private static function getSelect(array $over = []): array
    {
        $items = ['pdf' => ['title' => 'PDF', 'active' => true, 'sort' => 20], 'doc' => ['title' => 'DOC', 'active' => true, 'sort' => 10], 'old' => ['title' => 'Old', 'active' => false, 'sort' => 30]];
        return self::getRule($over + ['type' => 'select', 'options' => ['items' => $items]]);
    }

    # The first error code of one value against one definition, or an empty string
    private function getCode(array $rule, mixed $value, bool $required = true): string
    {
        return (new Field())->checkFieldValues(['probe' => $rule], ['probe' => $value], $required)['probe'] ?? '';
    }

    # The canonical stored value of one input, or null when it is absent
    private function getValue(array $rule, mixed $value): mixed
    {
        return (new Field())->filterFieldValues(['probe' => $rule], ['probe' => $value])['probe'] ?? null;
    }

    # The class is final, stateless and has exactly the six public methods of the contract with their exact signatures; no sibling classes or directory exist
    #[Test]
    public function theClassHasTheContractShape(): void
    {
        $ref = new ReflectionClass(Field::class);
        $this->assertTrue($ref->isFinal());
        $this->assertNull($ref->getConstructor(), 'Field declares a constructor');
        $this->assertSame([], $ref->getProperties(), 'Field has state');
        $want = [
            'getFieldTypeList' => 'array()',
            'filterFieldList' => 'array(array fields)',
            'checkFieldValues' => 'array(array fields, array values, bool required)',
            'filterFieldValues' => 'array(array fields, array values)',
            'getFieldForm' => 'array(Template tpl, array fields, array values, array errors)',
            'getFieldView' => 'array(Parser prs, array fields, array values, string module)',
        ];
        $have = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $meth) {
            $pars = [];
            foreach ($meth->getParameters() as $par) {
                $type = $par->getType();
                $pars[] = ($type instanceof ReflectionNamedType ? $type->getName() : '?').' '.$par->getName();
            }
            $back = $meth->getReturnType();
            $have[$meth->getName()] = ($back instanceof ReflectionNamedType ? $back->getName() : '?').'('.implode(', ', $pars).')';
        }
        $this->assertSame($want, $have);
        $root = dirname(__DIR__, 2).'/core/classes';
        $this->assertDirectoryDoesNotExist($root.'/field');
        foreach (['FieldManager', 'FieldService', 'FieldRegistry', 'FieldException', 'FieldError'] as $name) $this->assertFalse(class_exists($name, false), $name.' exists');
    }

    # The registry is exactly ten types, each with exactly four keys, and only select may hold several values
    #[Test]
    public function theRegistryIsClosed(): void
    {
        $list = (new Field())->getFieldTypeList();
        $want = [
            'text' => ['_FIELDINPUT', 'text', ['min', 'max']],
            'textarea' => ['_FIELDAREA', 'textarea', ['min', 'max']],
            'select' => ['_FIELDSELECT', 'select', ['items', 'min', 'max']],
            'bool' => ['_FIELDS_BOOL', 'checkbox', []],
            'int' => ['_FIELDS_INT', 'number', ['min', 'max']],
            'decimal' => ['_FIELDS_DECIMAL', 'number', ['min', 'max', 'scale']],
            'date' => ['_FIELDDATE', 'date', ['min', 'max']],
            'datetime' => ['_FIELDTIME', 'datetime', ['min', 'max']],
            'email' => ['_EMAIL', 'email', ['min', 'max']],
            'url' => ['_URL', 'url', ['min', 'max']],
        ];
        $this->assertSame(array_keys($want), array_keys($list));
        foreach ($want as $type => [$title, $control, $opts]) {
            $this->assertSame(['title' => $title, 'control' => $control, 'multi' => $type === 'select', 'options' => $opts], $list[$type], $type);
        }
    }

    # The three new type captions exist in all six administration locales
    #[Test]
    public function theNewCaptionsExistInEveryLocale(): void
    {
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
            foreach (['admin/lang' => ['_FIELDS_BOOL', '_FIELDS_INT', '_FIELDS_DECIMAL'], 'lang' => ['_FIELDS_REQ', '_FIELDS_TYPE', '_FIELDS_FORMAT', '_FIELDS_CHOICE', '_FIELDS_MIN', '_FIELDS_MAX']] as $dir => $names) {
                $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$dir.'/'.$lang.'.php');
                foreach ($names as $name) $this->assertSame(1, preg_match_all('/define\(\''.$name.'\'/', $code), $dir.'/'.$lang.'.php: '.$name);
            }
        }
    }

    # A valid set comes back whole, ordered by sort and then by name, with the keys of every definition and the options of a select in canonical order
    #[Test]
    public function aValidSetIsCanonical(): void
    {
        $set = ['zeta' => self::getRule(['sort' => 5]), 'beta' => self::getSelect(['sort' => 7]), 'alpha' => array_reverse(self::getRule(['sort' => 7]), true)];
        $out = (new Field())->filterFieldList($set);
        $this->assertSame(['zeta', 'alpha', 'beta'], array_keys($out));
        $this->assertSame(['title', 'intro', 'type', 'default', 'options', 'req', 'multi', 'active', 'sort'], array_keys($out['alpha']));
        $this->assertSame(['doc', 'pdf', 'old'], array_keys($out['beta']['options']['items']));
        $this->assertSame([], (new Field())->filterFieldList([]));
    }

    # Every wrong structure throws InvalidArgumentException with the path of the first error and nothing partial comes back
    #[Test]
    #[DataProvider('getBrokenSets')]
    public function aBrokenSetIsRefusedWithItsPath(array $set, string $path): void
    {
        try {
            (new Field())->filterFieldList($set);
            $this->fail('The set was accepted, expected '.$path);
        } catch (InvalidArgumentException $err) {
            $this->assertSame($path, $err->getMessage());
        }
    }

    public static function getBrokenSets(): array
    {
        $item = ['title' => 'A', 'active' => true, 'sort' => 1];
        $many = [];
        for ($i = 0; $i < 257; $i++) $many['f'.$i] = self::getRule();
        $opts = [];
        for ($i = 0; $i < 257; $i++) $opts['o'.$i] = $item;
        $rule = fn(array $over) => ['name' => self::getRule($over)];
        $pick = fn(array $over) => ['name' => self::getSelect($over)];
        return [
            'too many definitions' => [$many, 'fields'],
            'empty name' => [['' => self::getRule()], ''],
            'long name' => [[str_repeat('a', 33) => self::getRule()], str_repeat('a', 33)],
            'digit first' => [['2status' => self::getRule()], '2status'],
            'numeric name' => [[7 => self::getRule()], '7'],
            'upper case' => [['Release' => self::getRule()], 'Release'],
            'national' => [['статус' => self::getRule()], 'статус'],
            'hyphen' => [['some-value' => self::getRule()], 'some-value'],
            'not an array' => [['name' => 'text'], 'name'],
            'missing key' => [['name' => array_diff_key(self::getRule(), ['sort' => 0])], 'name.sort'],
            'unknown key' => [$rule(['class' => 'x']), 'name.class'],
            'callback key' => [$rule(['callback' => 'phpinfo']), 'name.callback'],
            'empty title' => [$rule(['title' => '']), 'name.title'],
            'long title' => [$rule(['title' => str_repeat('я', 256)]), 'name.title'],
            'markup in title' => [$rule(['title' => 'A <b>b</b>']), 'name.title'],
            'unknown constant' => [$rule(['title' => '_NO_SUCH_FIELD_CONST']), 'name.title'],
            'long intro' => [$rule(['intro' => str_repeat('я', 1001)]), 'name.intro'],
            'unknown type' => [$rule(['type' => 'file']), 'name.type'],
            'numeric legacy type' => [$rule(['type' => '3']), 'name.type'],
            'string req' => [$rule(['req' => '1']), 'name.req'],
            'string multi' => [$rule(['multi' => '0']), 'name.multi'],
            'int active' => [$rule(['active' => 1]), 'name.active'],
            'string sort' => [$rule(['sort' => '10']), 'name.sort'],
            'multi text' => [$rule(['multi' => true, 'default' => []]), 'name.multi'],
            'required but disabled' => [$rule(['req' => true, 'active' => false]), 'name.req'],
            'options not array' => [$rule(['options' => 'max=5']), 'name.options'],
            'unknown option' => [$rule(['options' => ['sql' => 'SELECT 1']]), 'name.options.sql'],
            'bool with option' => [$rule(['type' => 'bool', 'default' => null, 'options' => ['min' => 0]]), 'name.options.min'],
            'text max above ceiling' => [$rule(['options' => ['max' => 4097]]), 'name.options.max'],
            'text max zero' => [$rule(['options' => ['max' => 0]]), 'name.options.max'],
            'text min string' => [$rule(['options' => ['min' => '1']]), 'name.options.min'],
            'min above max' => [$rule(['options' => ['min' => 9, 'max' => 3]]), 'name.options.max'],
            'decimal without scale' => [$rule(['type' => 'decimal']), 'name.options.scale'],
            'decimal scale zero' => [$rule(['type' => 'decimal', 'options' => ['scale' => 0]]), 'name.options.scale'],
            'decimal scale nineteen' => [$rule(['type' => 'decimal', 'options' => ['scale' => 19]]), 'name.options.scale'],
            'decimal float limit' => [$rule(['type' => 'decimal', 'options' => ['scale' => 2, 'min' => 1.5]]), 'name.options.min'],
            'decimal limits crossed' => [$rule(['type' => 'decimal', 'options' => ['scale' => 2, 'min' => '2', 'max' => '-3']]), 'name.options.max'],
            'date limit unreal' => [$rule(['type' => 'date', 'options' => ['min' => '2026-02-30']]), 'name.options.min'],
            'select without items' => [$rule(['type' => 'select']), 'name.options.items'],
            'select too many items' => [$pick(['options' => ['items' => $opts]]), 'name.options.items'],
            'select numeric key' => [$pick(['options' => ['items' => [5 => $item]]]), 'name.options.items.5'],
            'select item extra key' => [$pick(['options' => ['items' => ['a' => $item + ['id' => 1]]]]), 'name.options.items.a.id'],
            'select item string sort' => [$pick(['options' => ['items' => ['a' => ['sort' => '1'] + $item]]]), 'name.options.items.a.sort'],
            'single select with limits' => [$pick(['options' => ['items' => ['a' => $item], 'max' => 2]]), 'name.options.max'],
            'multi select max above ceiling' => [$pick(['multi' => true, 'default' => [], 'options' => ['items' => ['a' => $item], 'max' => 65]]), 'name.options.max'],
            'default of wrong type' => [$rule(['type' => 'int', 'default' => '10']), 'name.default'],
            'bool default as string' => [$rule(['type' => 'bool', 'default' => '1']), 'name.default'],
            'default outside limits' => [$rule(['type' => 'int', 'default' => 11, 'options' => ['max' => 10]]), 'name.default'],
            'default is a disabled option' => [$pick(['default' => 'old']), 'name.default'],
            'multi default repeats nothing valid' => [$pick(['multi' => true, 'default' => ['none']]), 'name.default'],
            'single default as array' => [$pick(['default' => ['pdf']]), 'name.default'],
        ];
    }

    # Defaults are typed and canonical: a decimal is padded to its scale, a multiple select follows the order of the definition, and limits of a decimal take the same scale
    #[Test]
    public function defaultsAndLimitsAreCanonical(): void
    {
        $fld = new Field();
        $out = $fld->filterFieldList([
            'price' => self::getRule(['type' => 'decimal', 'default' => '1.2', 'options' => ['max' => '10', 'scale' => 2, 'min' => '-0.5']]),
            'kind' => self::getSelect(['multi' => true, 'default' => ['pdf', 'doc'], 'options' => self::getSelect()['options'] + ['max' => 2]]),
            'flag' => self::getRule(['type' => 'bool', 'default' => false]),
            'when' => self::getRule(['type' => 'datetime', 'options' => ['min' => '2026-01-01T10:00']]),
        ]);
        $this->assertSame('1.20', $out['price']['default']);
        $this->assertSame(['min' => '-0.50', 'max' => '10.00', 'scale' => 2], $out['price']['options']);
        $this->assertSame(['doc', 'pdf'], $out['kind']['default']);
        $this->assertFalse($out['flag']['default']);
        $this->assertSame('2026-01-01 10:00:00', $out['when']['options']['min']);
    }

    # One fixture per boundary drives both the check and the filter, so the two can never disagree: a code, or the canonical value
    #[Test]
    #[DataProvider('getValueCases')]
    public function checkAndFilterShareOneNormalization(array $rule, mixed $input, string $code, mixed $value): void
    {
        $this->assertSame($code, $this->getCode($rule, $input));
        if ($code === '') {
            $this->assertSame($value, $this->getValue($rule, $input));
            return;
        }
        $this->expectException(InvalidArgumentException::class);
        $this->getValue($rule, $input);
    }

    public static function getValueCases(): array
    {
        $text = self::getRule();
        $area = self::getRule(['type' => 'textarea']);
        $bool = self::getRule(['type' => 'bool', 'default' => null]);
        $int = self::getRule(['type' => 'int', 'default' => null, 'options' => ['min' => -5, 'max' => 5]]);
        $wide = self::getRule(['type' => 'int', 'default' => null]);
        $dec = self::getRule(['type' => 'decimal', 'options' => ['scale' => 2, 'min' => '-1.50', 'max' => '100']]);
        $big = self::getRule(['type' => 'decimal', 'options' => ['scale' => 1]]);
        $date = self::getRule(['type' => 'date', 'options' => ['min' => '2026-01-01', 'max' => '2026-12-31']]);
        $time = self::getRule(['type' => 'datetime']);
        $mail = self::getRule(['type' => 'email']);
        $url = self::getRule(['type' => 'url']);
        $one = self::getSelect();
        $multi = self::getSelect(['multi' => true, 'default' => [], 'options' => self::getSelect()['options'] + ['min' => 1, 'max' => 2]]);
        return [
            'text is trimmed' => [$text, '  one  ', '', 'one'],
            'text zero is a value' => [$text, '0', '', '0'],
            'text of spaces is absent' => [$text, '   ', '', null],
            'text line break' => [$text, "one\ntwo", 'format', null],
            'text array' => [$text, ['one'], 'type', null],
            'text at the ceiling' => [$text, str_repeat('я', 4096), '', str_repeat('я', 4096)],
            'text above the ceiling' => [$text, str_repeat('я', 4097), 'max', null],
            'text min counts characters' => [self::getRule(['options' => ['min' => 3, 'max' => 3]]), 'яяя', '', 'яяя'],
            'text below min' => [self::getRule(['options' => ['min' => 3]]), 'яя', 'min', null],
            'text above own max' => [self::getRule(['options' => ['max' => 3]]), 'яяяя', 'max', null],
            'textarea line ends and indents' => [$area, "  a\r\n\tb\rc ", '', "  a\n\tb\nc "],
            'textarea at the ceiling' => [$area, str_repeat('a', 262144), '', str_repeat('a', 262144)],
            'textarea above the ceiling' => [$area, str_repeat('a', 262145), 'max', null],
            'bool true' => [$bool, true, '', true],
            'bool false is a value' => [$bool, false, '', false],
            'bool one' => [$bool, 1, '', true],
            'bool string zero' => [$bool, '0', '', false],
            'bool word' => [$bool, 'yes', 'format', null],
            'bool two' => [$bool, 2, 'format', null],
            'bool array' => [$bool, [1], 'type', null],
            'int zero is a value' => [$int, 0, '', 0],
            'int from string' => [$int, '-5', '', -5],
            'int plus sign' => [$int, '+5', 'format', null],
            'int leading zero' => [$int, '05', 'format', null],
            'int minus zero' => [$int, '-0', 'format', null],
            'int float' => [$int, 1.5, 'type', null],
            'int below min' => [$int, -6, 'min', null],
            'int above max' => [$int, '6', 'max', null],
            'int top of 64 bits' => [$wide, '9223372036854775807', '', PHP_INT_MAX],
            'int over 64 bits' => [$wide, '9223372036854775808', 'format', null],
            'decimal is padded' => [$dec, '1.2', '', '1.20'],
            'decimal zero is a value' => [$dec, '0', '', '0.00'],
            'decimal minus zero' => [$dec, '-0.0', '', '0.00'],
            'decimal at min' => [$dec, '-1.5', '', '-1.50'],
            'decimal below min' => [$dec, '-1.51', 'min', null],
            'decimal at max' => [$dec, '100.00', '', '100.00'],
            'decimal above max' => [$dec, '100.01', 'max', null],
            'decimal exponent' => [$dec, '1e2', 'format', null],
            'decimal comma' => [$dec, '1,5', 'format', null],
            'decimal too fine' => [$dec, '1.234', 'format', null],
            'decimal float' => [$dec, 1.5, 'type', null],
            'decimal 65 digits' => [$big, str_repeat('9', 64).'.9', '', str_repeat('9', 64).'.9'],
            'decimal 66 digits' => [$big, str_repeat('9', 65).'.9', 'max', null],
            'date real' => [$date, '2026-02-28', '', '2026-02-28'],
            'date unreal' => [$date, '2026-02-30', 'format', null],
            'date localized' => [$date, '28.02.2026', 'format', null],
            'date before min' => [$date, '2025-12-31', 'min', null],
            'date after max' => [$date, '2027-01-01', 'max', null],
            'datetime html minutes' => [$time, '2026-03-01T09:05', '', '2026-03-01 09:05:00'],
            'datetime html seconds' => [$time, '2026-03-01T09:05:07', '', '2026-03-01 09:05:07'],
            'datetime canonical' => [$time, '2026-03-01 09:05:07', '', '2026-03-01 09:05:07'],
            'datetime space without seconds' => [$time, '2026-03-01 09:05', 'format', null],
            'datetime hour 24' => [$time, '2026-03-01T24:00', 'format', null],
            'email keeps the local case' => [$mail, ' John.Doe@Example.COM ', '', 'John.Doe@example.com'],
            'email broken' => [$mail, 'john@', 'format', null],
            'url https keeps case' => [$url, 'https://Example.com/Path?Q=A#F', '', 'https://Example.com/Path?Q=A#F'],
            'url http' => [$url, 'http://example.com', '', 'http://example.com'],
            'url root path' => [$url, '/Docs/Page?x=1', '', '/Docs/Page?x=1'],
            'url scheme relative' => [$url, '//example.com/x', 'format', null],
            'url credentials' => [$url, 'https://user:pass@example.com/', 'format', null],
            'url without scheme' => [$url, 'example.com/x', 'format', null],
            'url javascript' => [$url, 'javascript:alert(1)', 'format', null],
            'url data' => [$url, 'data:text/html,x', 'format', null],
            'url file' => [$url, 'file:///etc/passwd', 'format', null],
            'url with space' => [$url, 'https://example.com/a b', 'format', null],
            'url at the ceiling' => [$url, 'https://e.com/'.str_repeat('a', 2034), '', 'https://e.com/'.str_repeat('a', 2034)],
            'url above the ceiling' => [$url, 'https://e.com/'.str_repeat('a', 2035), 'max', null],
            'select key' => [$one, 'pdf', '', 'pdf'],
            'select empty is absent' => [$one, '', '', null],
            'select unknown' => [$one, 'zip', 'choice', null],
            'select disabled cannot be chosen anew' => [$one, 'old', 'choice', null],
            'select array for single' => [$one, ['pdf'], 'type', null],
            'multi order and repeats' => [$multi, ['pdf', 'doc', 'pdf'], '', ['doc', 'pdf']],
            'multi scalar' => [$multi, 'pdf', 'type', null],
            'multi unknown' => [$multi, ['pdf', 'zip'], 'choice', null],
            'multi empty is absent' => [$multi, [], '', null],
        ];
    }

    # Required is the only demand a draft lifts; limits stay, unknown names are no error and are dropped, and an inactive field is neither checked nor stored
    #[Test]
    public function requiredUnknownAndInactive(): void
    {
        $fld = new Field();
        $set = ['must' => self::getRule(['req' => true, 'options' => ['max' => 3]]), 'off' => self::getRule(['active' => false]), 'flag' => self::getRule(['type' => 'bool', 'default' => null, 'req' => true])];
        $this->assertSame(['flag' => 'required', 'must' => 'required'], $fld->checkFieldValues($set, []));
        $this->assertSame([], $fld->checkFieldValues($set, [], false));
        $this->assertSame(['must' => 'max'], $fld->checkFieldValues($set, ['must' => 'four', 'flag' => '0'], false));
        $this->assertSame([], $fld->checkFieldValues($set, ['must' => 'one', 'flag' => '0', 'ghost' => ['x'], 'off' => ['bad']]));
        $this->assertSame(['flag' => false, 'must' => 'one'], $fld->filterFieldValues($set, ['must' => ' one ', 'flag' => '0', 'ghost' => 'x', 'off' => 'kept elsewhere']));
    }

    # Sixty four chosen options pass and sixty five do not, whatever the definition says
    #[Test]
    public function theChoiceCeilingHolds(): void
    {
        $items = [];
        for ($i = 0; $i < 65; $i++) $items['o'.$i] = ['title' => 'O'.$i, 'active' => true, 'sort' => $i];
        $rule = self::getRule(['type' => 'select', 'multi' => true, 'default' => [], 'options' => ['items' => $items]]);
        $this->assertSame('', $this->getCode($rule, array_slice(array_keys($items), 0, 64)));
        $this->assertSame('max', $this->getCode($rule, array_keys($items)));
    }

    # The canonical JSON of all values stays inside one mebibyte: the set at the limit passes and the first byte above it is refused before any SQL
    #[Test]
    public function theJsonCeilingHolds(): void
    {
        $fld = new Field();
        $set = [];
        $vals = [];
        for ($i = 0; $i < 4; $i++) {
            $set['t'.$i] = self::getRule(['type' => 'textarea', 'sort' => $i]);
            $vals['t'.$i] = str_repeat('a', $i < 3 ? 262144 : 262111);
        }
        $this->assertSame([], $fld->checkFieldValues($set, $vals));
        $this->assertSame(1048576, strlen((string)json_encode($fld->filterFieldValues($set, $vals))), 'The fixture does not sit exactly on the ceiling');
        $vals['t3'] .= 'a';
        $this->assertSame(['t0' => 'max'], $fld->checkFieldValues($set, $vals));
        $this->expectException(InvalidArgumentException::class);
        $fld->filterFieldValues($set, $vals);
    }
}
