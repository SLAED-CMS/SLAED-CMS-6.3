<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The input filter of the API: a declared param is a type or [type, argument, 'def' => default], required without def, and only declared params reach the handler
final class ApiInput {

    # The types a declared param may take; a new type is added here and never checked inside a handler
    public const TYPES = ['num', 'word', 'text', 'json', 'ver'];

    # The declared params of one action from the query of a GET or HEAD, or from the JSON object of the body of any other method, read once and cut at $size bytes
    public static function getInput(array $params, string $method, int $size): array {
        if (in_array($method, ['GET', 'HEAD'], true)) return self::getValues($params, $_GET);
        return self::getValues($params, self::getBodyData((string)file_get_contents('php://input', false, null, 0, $size + 1), $size));
    }

    # The raw values of a body: longer than $size is 413, an empty body has none, anything but a JSON object is 400
    public static function getBodyData(string $body, int $size): array {
        if (strlen($body) > $size) throw new ApiError('too_large');
        if (trim($body) === '') return [];
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiError('bad_request');
        }
        if (!is_array($data) || !str_starts_with(ltrim($body), '{')) throw new ApiError('bad_request');
        return $data;
    }

    # The declared params out of the raw values: a missing required one or a value its type refuses is the 422 naming the param; undeclared names are dropped
    public static function getValues(array $params, array $data): array {
        $in = [];
        foreach ($params as $name => $spec) {
            $spec = is_array($spec) ? $spec : [$spec];
            if (!array_key_exists($name, $data)) {
                if (!array_key_exists('def', $spec)) throw new ApiError('invalid', ['param' => $name]);
                $in[$name] = $spec['def'];
                continue;
            }
            $val = self::filterValue($data[$name], $spec[0], $spec[1] ?? null);
            if ($val === null) throw new ApiError('invalid', ['param' => $name]);
            $in[$name] = $val;
        }
        return $in;
    }

    # Whether a declaration of a param is one this filter reads: a known type, an argument of the shape its type takes, and a default its own type accepts
    public static function checkSpec(mixed $spec): bool {
        $spec = is_array($spec) ? $spec : [$spec];
        if (!in_array($spec[0] ?? null, self::TYPES, true) || array_diff(array_keys($spec), [0, 1, 'def']) !== []) return false;
        $arg = $spec[1] ?? null;
        $good = match ($spec[0]) {
            'num' => $arg === null || (is_int($arg) && $arg >= 0),
            'word' => $arg === null || (is_array($arg) && $arg !== [] && array_filter($arg, fn($v): bool => self::filterValue($v, 'word', null) === null) === []),
            'text' => $arg === null || (is_int($arg) && $arg > 0),
            default => $arg === null,
        };
        return $good && (!array_key_exists('def', $spec) || self::filterValue($spec['def'], $spec[0], $arg) !== null);
    }

    # One value by its type, or null when the type refuses it; a query value that is an array is refused by every type
    private static function filterValue(mixed $val, string $type, mixed $arg): mixed {
        return match ($type) {
            'num' => match (true) {
                is_int($val) && $val >= 0 => ($arg === null || $val <= $arg) ? $val : null,
                is_string($val) && preg_match('#^\d{1,18}$#D', $val) === 1 => ($arg === null || (int)$val <= $arg) ? (int)$val : null,
                default => null,
            },
            'word' => (is_string($val) && preg_match('#^[A-Za-z0-9_-]{1,64}$#D', $val) === 1 && ($arg === null || in_array($val, $arg, true))) ? $val : null,
            'text' => (is_string($val) && mb_check_encoding($val, 'UTF-8') && preg_match('#[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]#', $val) !== 1
                && mb_strlen($val, 'UTF-8') <= ($arg ?? 255)) ? $val : null,
            'json' => is_array($val) ? $val : (is_string($val) ? self::getJsonValue($val) : null),
            'ver' => (is_string($val) && preg_match('#^\d{1,5}\.\d{1,5}\.\d{1,5}$#D', $val) === 1) ? $val : null,
            default => null,
        };
    }

    # A JSON text of the query decoded into an array, or null for anything else
    private static function getJsonValue(string $val): ?array {
        $data = json_decode($val, true, 32);
        return is_array($data) ? $data : null;
    }
}
