<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Extra fields of every area of the system: one closed registry of types, one check of definitions, one normalization of values and one preparation of the form and of the view
# The class has no constructor, reads neither the configuration nor the database and writes nothing; definitions and values are always handed in by the caller
# A definition set is accepted as a whole or refused with the path of its first error, and a value is never repaired silently: it is canonical, absent, or an error code
final class Field {

    # The grammar shared by the name of a field and the key of a select option: a stored key never starts with a digit, so PHP cannot turn it into an integer
    private const NAME = '/^[a-z][a-z0-9_]{0,31}$/D';

    # The exact keys of one definition in their canonical order, and the exact keys of one select option
    private const KEYS = ['title', 'intro', 'type', 'default', 'options', 'req', 'multi', 'active', 'sort'];
    private const ITEM = ['title', 'active', 'sort'];

    # The closed registry: the language constant naming the type, the semantic key of the form control, whether several values may be allowed and the options it accepts
    private const TYPES = [
        'text' => ['title' => '_FIELDINPUT', 'control' => 'text', 'multi' => false, 'options' => ['min', 'max']],
        'textarea' => ['title' => '_FIELDAREA', 'control' => 'textarea', 'multi' => false, 'options' => ['min', 'max']],
        'select' => ['title' => '_FIELDSELECT', 'control' => 'select', 'multi' => true, 'options' => ['items', 'min', 'max']],
        'bool' => ['title' => '_FIELDS_BOOL', 'control' => 'checkbox', 'multi' => false, 'options' => []],
        'int' => ['title' => '_FIELDS_INT', 'control' => 'number', 'multi' => false, 'options' => ['min', 'max']],
        'decimal' => ['title' => '_FIELDS_DECIMAL', 'control' => 'number', 'multi' => false, 'options' => ['min', 'max', 'scale']],
        'date' => ['title' => '_FIELDDATE', 'control' => 'date', 'multi' => false, 'options' => ['min', 'max']],
        'datetime' => ['title' => '_FIELDTIME', 'control' => 'datetime', 'multi' => false, 'options' => ['min', 'max']],
        'email' => ['title' => '_EMAIL', 'control' => 'email', 'multi' => false, 'options' => ['min', 'max']],
        'url' => ['title' => '_URL', 'control' => 'url', 'multi' => false, 'options' => ['min', 'max']],
    ];

    # The hard ceilings no definition can raise: characters of the string types, definitions of a set and options of a select, chosen options, digits of a decimal, JSON bytes
    private const LENGTH = ['text' => 4096, 'textarea' => 262144, 'email' => 254, 'url' => 2048];
    private const MAXSET = 256;
    private const MAXPICK = 64;
    private const MAXNUM = 65;
    private const MAXJSON = 1048576;

    # The language constants behind the six machine codes of a refused value
    private const TEXTS = [
        'required' => '_FIELDS_REQ', 'type' => '_FIELDS_TYPE', 'format' => '_FIELDS_FORMAT', 'choice' => '_FIELDS_CHOICE', 'min' => '_FIELDS_MIN', 'max' => '_FIELDS_MAX',
    ];

    # Answer the closed registry of field types; a new type is added here in code together with its tests and never through the administration form
    public function getFieldTypeList(): array {
        return self::TYPES;
    }

    # Check a whole set of definitions and answer it canonical, ordered by sort and then by name, or throw InvalidArgumentException with the path of the first error
    # Nothing is completed, cut or repaired: a missing key, an unknown key and a value of the wrong native type are each an error of the definition
    public function filterFieldList(array $fields): array {
        if (count($fields) > self::MAXSET) throw new InvalidArgumentException('fields');
        $out = [];
        foreach ($fields as $name => $def) {
            if (!is_string($name) || !preg_match(self::NAME, $name)) throw new InvalidArgumentException($name);
            $out[$name] = $this->filterFieldRule($name, $def);
        }
        uksort($out, fn(string $a, string $b): int => [$out[$a]['sort'], $a] <=> [$out[$b]['sort'], $b]);
        return $out;
    }

    # Check the values of the active fields and answer field name => first error code, an empty array meaning success; unknown input names are no error
    # Type, format and limits are always checked; $required = false only lifts the demand to fill a field of an unfinished draft
    public function checkFieldValues(array $fields, array $values, bool $required = true): array {
        $errs = [];
        $data = [];
        foreach ($this->filterFieldList($fields) as $name => $rule) {
            if (!$rule['active']) continue;
            [$code, $val] = $this->getFieldValue($rule, $values[$name] ?? null);
            if ($code === '' && $val === null && $rule['req'] && $required) $code = 'required';
            if ($code !== '') $errs[$name] = $code;
            elseif ($val !== null) $data[$name] = $val;
        }
        if ($errs || strlen($this->getFieldJson($data)) <= self::MAXJSON) return $errs;
        $size = array_map(fn(mixed $v): int => strlen($this->getFieldJson([$v])), $data);
        return [array_search(max($size), $size, true) => 'max'];
    }

    # Answer the canonical typed values of the active fields for storing: unknown names are dropped and absent values are left out
    # The caller checks first; a value that still fails here is thrown as InvalidArgumentException and never stored repaired
    public function filterFieldValues(array $fields, array $values): array {
        $out = [];
        foreach ($this->filterFieldList($fields) as $name => $rule) {
            if (!$rule['active']) continue;
            [$code, $val] = $this->getFieldValue($rule, $values[$name] ?? null);
            if ($code !== '') throw new InvalidArgumentException($name.': '.$code);
            if ($val !== null) $out[$name] = $val;
        }
        if (strlen($this->getFieldJson($out)) > self::MAXJSON) throw new InvalidArgumentException('values: max');
        return $out;
    }

    # Prepare the standard form rows of the active fields, indexed by field name; values and error codes always come from the controller and POST is never read here
    # A value is shown as it was given, canonical or just posted, and an error code becomes the shared language message of its row
    public function getFieldForm(Template $tpl, array $fields, array $values = [], array $errors = []): array {
        $rows = [];
        foreach ($this->filterFieldList($fields) as $name => $rule) {
            if (!$rule['active']) continue;
            $fid = 'f-field-'.$name;
            $hint = $this->getFieldText($rule['intro']);
            $code = self::TEXTS[$errors[$name] ?? ''] ?? '';
            $error = ($code !== '' && defined($code)) ? constant($code) : '';
            $base = ['name_attr' => 'field['.$name.']', 'input_id' => $fid, 'is_required' => $rule['req'], 'describedby' => ($hint !== '' || $error !== '') ? $fid.'-hint' : ''];
            $rows[$name] = [
                'name' => $name,
                'type' => $rule['type'],
                'label_for' => $fid,
                'label_text' => $this->getFieldText($rule['title']),
                'hint_id' => $base['describedby'],
                'hint_text' => $hint,
                'error_text' => $error,
                'is_required' => $rule['req'],
                'field_html' => $this->getFieldControl($tpl, $rule, $base, $values[$name] ?? null),
            ];
        }
        return $rows;
    }

    # Prepare the safe public view of the stored values, indexed by field name in canonical order; templates keep the HTML and only a textarea gets parser output
    # Inactive fields, absent values, stored names without a definition and removed options are left out, while false, 0 and a stored disabled option are shown
    public function getFieldView(Parser $prs, array $fields, array $values, string $module): array {
        $out = [];
        foreach ($this->filterFieldList($fields) as $name => $rule) {
            if (!$rule['active']) continue;
            $loose = $rule;
            unset($loose['options']['min'], $loose['options']['max']);
            [$code, $val] = $this->getFieldValue($loose, $values[$name] ?? null, true);
            if ($code !== '' || $val === null || $val === []) continue;
            $items = [];
            $opts = $rule['options']['items'] ?? [];
            foreach ($rule['type'] === 'select' ? (array)$val : [] as $key) $items[] = ['value' => $key, 'label_text' => $this->getFieldText($opts[$key]['title'])];
            $text = match ($rule['type']) {
                'select' => implode(', ', array_column($items, 'label_text')),
                'bool' => $val ? _YES : _NO,
                default => (string)$val,
            };
            $out[$name] = [
                'name' => $name,
                'type' => $rule['type'],
                'label_text' => $this->getFieldText($rule['title']),
                'hint_text' => $this->getFieldText($rule['intro']),
                'value' => $val,
                'value_text' => $text,
                'value_html' => $rule['type'] === 'textarea' ? $prs->filterContent($val, true, $module) : '',
                'value_href' => match ($rule['type']) {
                    'url' => $val,
                    'email' => 'mailto:'.$val,
                    default => '',
                },
                'items' => $items,
            ];
        }
        return $out;
    }

    # Encode values the one canonical way, so the size limit is measured on the same bytes the owner stores
    private function getFieldJson(array $data): string {
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    # Resolve a title or a hint: a leading underscore always names a language constant, anything else is literal plain text
    private function getFieldText(string $text): string {
        if ($text === '' || $text[0] !== '_') return $text;
        return defined($text) ? constant($text) : '';
    }

    # Check one title or hint: plain text without markup and control characters inside its Unicode length, or the name of an existing language constant
    private function checkFieldText(mixed $text, int $max, bool $empty): bool {
        if (!is_string($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > $max) return false;
        if ($text === '') return $empty;
        if ($text[0] === '_') return defined($text) && $this->checkSiteWord($text);
        return $text === trim($text) && $text === strip_tags($text) && !preg_match('/[\x00-\x1F\x7F<>]/', $text);
    }

    # A caption constant has to live in the site dictionary: a site page loads lang/<language>.php alone, so there defined() is the answer, while the panel and the installer
    # load dictionaries of their own on top, and a constant of those would pass a save and then empty the whole set on the site; the loaded site file is read once per request
    private function checkSiteWord(string $name): bool {
        static $words = null;
        if (!defined('ADMIN_FILE') && !defined('SETUP_FILE')) return true;
        if ($words === null) {
            $words = [];
            $root = strtolower(str_replace('\\', '/', BASE_DIR).'/lang/');
            foreach (get_included_files() as $file) {
                $path = strtolower(str_replace('\\', '/', $file));
                if (!str_starts_with($path, $root) || str_contains(substr($path, strlen($root)), '/')) continue;
                preg_match_all("#^define\('(_[A-Z0-9_]+)'#m", (string)file_get_contents($file), $hit);
                $words = array_flip($hit[1]);
                break;
            }
        }
        return isset($words[$name]);
    }

    # Check one definition and answer it canonical: the nine keys in fixed order, native types only, options of the type, and a default that is itself a valid value
    # No default is an empty string for the string based types, an empty array for a multiple select and null for bool and int, whose every native value is a real value
    private function filterFieldRule(string $name, mixed $def): array {
        if (!is_array($def)) throw new InvalidArgumentException($name);
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $def)) throw new InvalidArgumentException($name.'.'.$key);
        }
        foreach (array_keys($def) as $key) {
            if (!in_array($key, self::KEYS, true)) throw new InvalidArgumentException($name.'.'.$key);
        }
        if (!$this->checkFieldText($def['title'], 255, false)) throw new InvalidArgumentException($name.'.title');
        if (!$this->checkFieldText($def['intro'], 1000, true)) throw new InvalidArgumentException($name.'.intro');
        if (!is_string($def['type']) || !isset(self::TYPES[$def['type']])) throw new InvalidArgumentException($name.'.type');
        foreach (['req', 'multi', 'active'] as $key) {
            if (!is_bool($def[$key])) throw new InvalidArgumentException($name.'.'.$key);
        }
        if (!is_int($def['sort'])) throw new InvalidArgumentException($name.'.sort');
        if ($def['multi'] && !self::TYPES[$def['type']]['multi']) throw new InvalidArgumentException($name.'.multi');
        if ($def['req'] && !$def['active']) throw new InvalidArgumentException($name.'.req');
        if (!is_array($def['options'])) throw new InvalidArgumentException($name.'.options');
        $rule = ['title' => $def['title'], 'intro' => $def['intro'], 'type' => $def['type'], 'default' => $def['default'], 'options' => []];
        $rule += ['req' => $def['req'], 'multi' => $def['multi'], 'active' => $def['active'], 'sort' => $def['sort']];
        $rule['options'] = $this->filterFieldOpts($name, $rule, $def['options']);
        $none = match (true) {
            $rule['multi'] => [],
            in_array($rule['type'], ['bool', 'int'], true) => null,
            default => '',
        };
        if ($def['default'] === $none) return $rule;
        $native = match (true) {
            $rule['multi'] => is_array($def['default']),
            $rule['type'] === 'bool' => is_bool($def['default']),
            $rule['type'] === 'int' => is_int($def['default']),
            default => is_string($def['default']),
        };
        [$code, $val] = $native ? $this->getFieldValue($rule, $def['default']) : ['type', null];
        if ($code !== '' || $val === null || $val === []) throw new InvalidArgumentException($name.'.default');
        $rule['default'] = $val;
        return $rule;
    }

    # Check the options of one definition against its type and answer them canonical in the order items, min, max, scale; any key the type does not accept is an error
    # Limits are inclusive and typed: characters for strings, whole numbers for int, exact strings for decimal, canonical moments for dates, a count for a multiple select
    private function filterFieldOpts(string $name, array $rule, array $opts): array {
        $type = $rule['type'];
        $path = $name.'.options.';
        foreach (array_keys($opts) as $key) {
            if (!in_array($key, self::TYPES[$type]['options'], true)) throw new InvalidArgumentException($path.$key);
        }
        $out = [];
        if ($type === 'select') {
            $out['items'] = $this->filterFieldItems($path.'items', $opts['items'] ?? null);
            if (!$rule['multi'] && (isset($opts['min']) || isset($opts['max']))) throw new InvalidArgumentException($path.(isset($opts['min']) ? 'min' : 'max'));
        }
        if ($type === 'decimal') {
            $scale = $opts['scale'] ?? null;
            if (!is_int($scale) || $scale < 1 || $scale > 18) throw new InvalidArgumentException($path.'scale');
        }
        $top = self::LENGTH[$type] ?? ($type === 'select' ? self::MAXPICK : null);
        foreach (['min', 'max'] as $key) {
            if (!array_key_exists($key, $opts)) continue;
            $val = $opts[$key];
            if ($top !== null || $type === 'int') {
                $good = is_int($val) && ($type === 'int' || ($val >= ($key === 'max' ? 1 : 0) && $val <= $top));
            } else {
                $probe = ['options' => ($type === 'decimal') ? ['scale' => $opts['scale']] : []] + $rule;
                [$code, $val] = is_string($val) ? $this->getFieldValue($probe, $val) : ['type', null];
                $good = $code === '' && $val !== null;
            }
            if (!$good) throw new InvalidArgumentException($path.$key);
            $out[$key] = $val;
        }
        if (isset($out['min'], $out['max'])) {
            $order = ($type === 'decimal') ? $this->getDecimalOrder($out['min'], $out['max']) : $out['min'] <=> $out['max'];
            if ($order > 0) throw new InvalidArgumentException($path.'max');
        }
        if ($type === 'decimal') $out['scale'] = $opts['scale'];
        return $out;
    }

    # Check the options of a select and answer them ordered by sort and then by key: a stable stored key, a title, a switch and an order, nothing else
    private function filterFieldItems(string $path, mixed $items): array {
        if (!is_array($items) || !$items || count($items) > self::MAXSET) throw new InvalidArgumentException($path);
        $out = [];
        foreach ($items as $key => $item) {
            $step = $path.'.'.$key;
            if (!is_string($key) || !preg_match(self::NAME, $key) || !is_array($item)) throw new InvalidArgumentException($step);
            foreach (self::ITEM as $part) {
                if (!array_key_exists($part, $item)) throw new InvalidArgumentException($step.'.'.$part);
            }
            foreach (array_keys($item) as $part) {
                if (!in_array($part, self::ITEM, true)) throw new InvalidArgumentException($step.'.'.$part);
            }
            if (!$this->checkFieldText($item['title'], 255, false)) throw new InvalidArgumentException($step.'.title');
            if (!is_bool($item['active'])) throw new InvalidArgumentException($step.'.active');
            if (!is_int($item['sort'])) throw new InvalidArgumentException($step.'.sort');
            $out[$key] = ['title' => $item['title'], 'active' => $item['active'], 'sort' => $item['sort']];
        }
        uksort($out, fn(string $a, string $b): int => [$out[$a]['sort'], $a] <=> [$out[$b]['sort'], $b]);
        return $out;
    }

    # The one normalization behind both the check and the filter: answer [error code, canonical value], an empty code with null meaning the value is absent
    # Null, an empty string and an empty array are absence, while false, 0 and 0.00 are values; $old reads a stored value and so keeps a disabled select option
    private function getFieldValue(array $rule, mixed $raw, bool $old = false): array {
        if ($raw === null || $raw === '' || $raw === []) return ['', null];
        $opts = $rule['options'];
        if ($rule['type'] === 'select') return $this->getSelectValue($rule, $raw, $old);
        if ($rule['type'] === 'bool') {
            $pos = array_search($raw, [true, false, 1, 0, '1', '0'], true);
            return ($pos === false) ? [is_scalar($raw) ? 'format' : 'type', null] : ['', $pos % 2 === 0];
        }
        if ($rule['type'] === 'int') {
            if (is_string($raw) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $raw) && $raw !== '-0' && (string)intval($raw) === $raw) $raw = intval($raw);
            if (!is_int($raw)) return [is_string($raw) ? 'format' : 'type', null];
            if (isset($opts['min']) && $raw < $opts['min']) return ['min', null];
            return (isset($opts['max']) && $raw > $opts['max']) ? ['max', null] : ['', $raw];
        }
        if (!is_string($raw)) return ['type', null];
        if (!mb_check_encoding($raw, 'UTF-8')) return ['format', null];
        if ($rule['type'] === 'decimal') {
            $val = $this->getDecimalText($raw, $opts['scale']);
            if ($val === '') return ['format', null];
            if (strlen(ltrim($val, '-')) - 1 > self::MAXNUM) return ['max', null];
            if (isset($opts['min']) && $this->getDecimalOrder($val, $opts['min']) < 0) return ['min', null];
            return (isset($opts['max']) && $this->getDecimalOrder($val, $opts['max']) > 0) ? ['max', null] : ['', $val];
        }
        if ($rule['type'] === 'date' || $rule['type'] === 'datetime') {
            $val = $this->getMomentText($raw, $rule['type'] === 'datetime');
            if ($val === '') return ['format', null];
            if (isset($opts['min']) && strcmp($val, $opts['min']) < 0) return ['min', null];
            return (isset($opts['max']) && strcmp($val, $opts['max']) > 0) ? ['max', null] : ['', $val];
        }
        $val = ($rule['type'] === 'textarea') ? str_replace(["\r\n", "\r"], "\n", $raw) : trim($raw);
        if ($val === '') return ['', null];
        $good = match ($rule['type']) {
            'text' => !preg_match('/[\x00-\x1F\x7F]/', $val),
            'textarea' => !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $val),
            'email' => filter_var($val, FILTER_VALIDATE_EMAIL) !== false,
            'url' => $this->checkFieldUrl($val),
        };
        if (!$good) return ['format', null];
        if ($rule['type'] === 'email') $val = substr($val, 0, strrpos($val, '@')).strtolower(substr($val, strrpos($val, '@')));
        $size = mb_strlen($val, 'UTF-8');
        if ($size < ($opts['min'] ?? 0)) return ['min', null];
        return ($size > min($opts['max'] ?? self::LENGTH[$rule['type']], self::LENGTH[$rule['type']])) ? ['max', null] : ['', $val];
    }

    # Normalize the choice of a select: one stored key, or the chosen keys of a multiple select without repeats and in the order of the definition
    # A disabled option cannot be chosen anew; read as a stored value it keeps its place, and a stored key whose option is gone is left out instead of failing the rest
    private function getSelectValue(array $rule, mixed $raw, bool $old): array {
        $items = $rule['options']['items'];
        $list = $rule['multi'] ? $raw : [$raw];
        if (!is_array($list)) return ['type', null];
        $pick = [];
        foreach ($list as $key) {
            if (!is_string($key)) return ['type', null];
            if (!isset($items[$key]) && $old) continue;
            if (!isset($items[$key]) || (!$old && !$items[$key]['active'])) return ['choice', null];
            $pick[$key] = true;
        }
        if (!$rule['multi']) return ['', $pick ? $raw : null];
        $vals = array_values(array_filter(array_keys($items), fn(int|string $v): bool => isset($pick[$v])));
        if (count($vals) < ($rule['options']['min'] ?? 0)) return ['min', null];
        return (count($vals) > min($rule['options']['max'] ?? self::MAXPICK, self::MAXPICK)) ? ['max', null] : ['', $vals];
    }

    # Answer a decimal as a string with a point and exactly $scale fraction digits, or an empty string: no exponent, no comma, no plus, no spare leading zeros, no cut digits
    private function getDecimalText(string $raw, int $scale): string {
        if (!preg_match('/^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', $raw, $part)) return '';
        $frac = $part[3] ?? '';
        if (strlen($frac) > $scale) return '';
        $frac = str_pad($frac, $scale, '0');
        return (($part[1] === '-' && trim($part[2].$frac, '0') !== '') ? '-' : '').$part[2].'.'.$frac;
    }

    # Compare two canonical decimals of one scale without float: the sign first, then the length of the digits, then the digits themselves
    private function getDecimalOrder(string $left, string $right): int {
        $nega = $left[0] === '-';
        if ($nega !== ($right[0] === '-')) return $nega ? -1 : 1;
        [$left, $right] = [ltrim($left, '-'), ltrim($right, '-')];
        $order = (strlen($left) <=> strlen($right)) ?: (strcmp($left, $right) <=> 0);
        return $nega ? -$order : $order;
    }

    # Answer a real calendar date as YYYY-MM-DD, or a real moment as YYYY-MM-DD HH:MM:SS from the strict HTML form with T or from the canonical form, or an empty string
    private function getMomentText(string $raw, bool $time): string {
        $date = '([0-9]{4})-([0-9]{2})-([0-9]{2})';
        $mask = '/^'.$date.($time ? '(?:T([0-9]{2}):([0-9]{2})(?::([0-9]{2}))?| ([0-9]{2}):([0-9]{2}):([0-9]{2}))' : '').'$/D';
        if (!preg_match($mask, $raw, $part) || !checkdate(intval($part[2]), intval($part[3]), intval($part[1]))) return '';
        if (!$time) return $raw;
        $hms = isset($part[7]) ? [$part[7], $part[8], $part[9]] : [$part[4], $part[5], ($part[6] ?? '') ?: '00'];
        if ($hms[0] > '23' || $hms[1] > '59' || $hms[2] > '59') return '';
        return substr($raw, 0, 10).' '.implode(':', $hms);
    }

    # Check an address: an absolute http or https URL without credentials, or a path from the site root; case is kept, nothing is completed and filterWebUrl() is not used
    private function checkFieldUrl(string $url): bool {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) return false;
        if ($url[0] === '/') return !str_starts_with($url, '//');
        return preg_match('#^https?://[^/?\#@]+(?:[/?\#].*)?$#iD', $url) === 1;
    }

    # Build the standard control of one field from the shared fragments; the semantic control key of the registry picks it and the theme keeps every class
    # A checkbox is preceded by a hidden zero, so an unchecked box posts false instead of nothing; a moment is shown in the HTML form its input expects
    private function getFieldControl(Template $tpl, array $rule, array $base, mixed $val): string {
        $opts = $rule['options'];
        $control = self::TYPES[$rule['type']]['control'];
        if ($control === 'select') {
            $pick = array_flip(array_filter((array)$val, 'is_string'));
            $html = $rule['multi'] ? '' : $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NO, 'is_selected' => !$pick]);
            foreach ($opts['items'] as $key => $item) {
                $text = $this->getFieldText($item['title']);
                if ($item['active']) $html .= $tpl->getHtmlFrag('select-option', ['value_attr' => $key, 'label_text' => $text, 'is_selected' => isset($pick[$key])]);
            }
            $more = ['selectid' => $base['input_id'], 'options_html' => $html, 'is_multiple' => $rule['multi'], 'is_name_array' => $rule['multi']];
            $more['select_attr'] = $rule['req'] ? 'required' : '';
            return $tpl->getHtmlFrag('select', $more + $base);
        }
        if ($control === 'checkbox') {
            $on = in_array($val, [true, 1, '1'], true);
            $zero = $tpl->getHtmlFrag('hidden', ['name_attr' => $base['name_attr'], 'value_attr' => '0']);
            return $zero.$tpl->getHtmlFrag('checkbox', ['value_attr' => '1', 'is_checked' => $on, 'is_required' => false] + $base);
        }
        $text = is_scalar($val) ? (string)$val : '';
        if ($control === 'textarea') return $tpl->getHtmlFrag('textarea', ['value_text' => $text, 'rows_num' => 5] + $base);
        if ($control === 'datetime') $text = str_replace(' ', 'T', $text);
        $more = ['itype' => ($control === 'datetime') ? 'datetime-local' : $control, 'value_attr' => $text];
        if (isset(self::LENGTH[$rule['type']])) $more['maxlength_num'] = min($opts['max'] ?? self::LENGTH[$rule['type']], self::LENGTH[$rule['type']]);
        if ($control === 'number') $more['input_attr'] = 'step="'.(isset($opts['scale']) ? '0.'.str_repeat('0', $opts['scale'] - 1).'1' : '1').'"';
        return $tpl->getHtmlFrag('input', $more + $base);
    }
}
