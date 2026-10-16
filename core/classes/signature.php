<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Ed25519 signature check for a site without the extension sodium, after crypto_sign_open of TweetNaCl (public domain), 64-bit PHP only
final class Signature {

    # Field elements of 2^255-19 as sixteen 16-bit limbs: the curve constant d, 2d, the base point and the square root of -1
    private const CURVE_D = [0x78a3, 0x1359, 0x4dca, 0x75eb, 0xd8ab, 0x4141, 0x0a4d, 0x0070, 0xe898, 0x7779, 0x4079, 0x8cc7, 0xfe73, 0x2b6f, 0x6cee, 0x5203];
    private const CURVE_DD = [0xf159, 0x26b2, 0x9b94, 0xebd6, 0xb156, 0x8283, 0x149a, 0x00e0, 0xd130, 0xeef3, 0x80f2, 0x198e, 0xfce7, 0x56df, 0xd9dc, 0x2406];
    private const BASE_X = [0xd51a, 0x8f25, 0x2d60, 0xc956, 0xa7b2, 0x9525, 0xc760, 0x692c, 0xdc5c, 0xfdd6, 0xe231, 0xc0a4, 0x53fe, 0xcd6e, 0x36d3, 0x2169];
    private const BASE_Y = [0x6658, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666, 0x6666];
    private const ROOT = [0xa0b0, 0x4a0e, 0x1b27, 0xc4ee, 0xe478, 0xad2f, 0x1806, 0x2f43, 0xd7a7, 0x3dfb, 0x0099, 0x2b4d, 0xdf0b, 0x4fc1, 0x2480, 0x2b83];
    private const ONE = [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    private const ZERO = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

    # The group order L in little-endian bytes
    private const ORDER = [0xed, 0xd3, 0xf5, 0x5c, 0x1a, 0x63, 0x12, 0x58, 0xd6, 0x9c, 0xf7, 0xa2, 0xde, 0xf9, 0xde, 0x14, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0x10];

    # The encodings of the points of small order that libsodium refuses as a key or as R, compared with the sign bit cleared
    private const SMALL = ['0000000000000000000000000000000000000000000000000000000000000000', '0100000000000000000000000000000000000000000000000000000000000000',
        '26e8958fc2b227b045c3f489f2ef98f0d5dfac05d3c63339b13802886d53fc05', 'c7176a703d4dd84fba3c0b760d10670f2a2053fa2c39ccc64ec7fd7792ac037a',
        'ecffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7f', 'edffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7f',
        'eeffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7f'];

    # Whether $sig signs $msg under the raw public $key, with the refusals of libsodium: S below L, a canonical key, no small order
    public static function isSignValid(string $msg, string $sig, string $key): bool {
        if (PHP_INT_SIZE !== 8 || strlen($sig) !== 64 || strlen($key) !== 32) return false;
        $num = array_values(unpack('C32', $sig, 32));
        if (!self::isScalarValid($num) || self::isPointSmall(substr($sig, 0, 32)) || self::isPointSmall($key)) return false;
        $pnt = self::getPointNegated($key);
        if ($pnt === null) return false;
        $hash = self::getScalarReduced(array_values(unpack('C64', hash('sha512', substr($sig, 0, 32).$key.$msg, true))));
        $base = [self::BASE_X, self::BASE_Y, self::ONE, self::getFieldProduct(self::BASE_X, self::BASE_Y)];
        $sum = self::getPointSum(self::getPointMultiple($pnt, $hash), self::getPointMultiple($base, $num));
        return hash_equals(substr($sig, 0, 32), self::getPointBytes($sum));
    }

    # Whether the little-endian scalar is below the group order, so no second encoding of the same S passes
    private static function isScalarValid(array $num): bool {
        for ($i = 31; $i >= 0; --$i) {
            if ($num[$i] !== self::ORDER[$i]) return $num[$i] < self::ORDER[$i];
        }
        return false;
    }

    # Whether a point encoding names one of the points of small order
    private static function isPointSmall(string $raw): bool {
        return in_array(bin2hex(substr($raw, 0, 31).chr(ord($raw[31]) & 0x7f)), self::SMALL, true);
    }

    # The limbs carried once into 16 bits each, the overflow of the top limb folded back as 38 times
    private static function getFieldCarry(array $num): array {
        for ($i = 0; $i < 16; ++$i) {
            $num[$i] += 65536;
            $cy = $num[$i] >> 16;
            if ($i < 15) $num[$i + 1] += $cy - 1;
            else $num[0] += 38 * ($cy - 1);
            $num[$i] -= $cy << 16;
        }
        return $num;
    }

    # The sum of two field elements, limb by limb
    private static function getFieldSum(array $one, array $two): array {
        for ($i = 0; $i < 16; ++$i) $one[$i] += $two[$i];
        return $one;
    }

    # The difference of two field elements, limb by limb
    private static function getFieldDiff(array $one, array $two): array {
        for ($i = 0; $i < 16; ++$i) $one[$i] -= $two[$i];
        return $one;
    }

    # The product of two field elements, the upper half folded back as 38 times and carried twice
    private static function getFieldProduct(array $one, array $two): array {
        $tmp = array_fill(0, 31, 0);
        for ($i = 0; $i < 16; ++$i) {
            $cur = $one[$i];
            for ($j = 0; $j < 16; ++$j) $tmp[$i + $j] += $cur * $two[$j];
        }
        for ($i = 0; $i < 15; ++$i) $tmp[$i] += 38 * $tmp[$i + 16];
        return self::getFieldCarry(self::getFieldCarry(array_slice($tmp, 0, 16)));
    }

    # The inverse of a field element as its power p-2, or with $inv false its power (p-5)/8 for the square root
    private static function getFieldPower(array $num, bool $inv): array {
        $res = $num;
        for ($i = $inv ? 253 : 250; $i >= 0; --$i) {
            $res = self::getFieldProduct($res, $res);
            if ($inv ? ($i !== 2 && $i !== 4) : ($i !== 1)) $res = self::getFieldProduct($res, $num);
        }
        return $res;
    }

    # The canonical 32 little-endian bytes of a field element, reduced below p
    private static function getFieldBytes(array $num): string {
        $tmp = self::getFieldCarry(self::getFieldCarry(self::getFieldCarry($num)));
        for ($j = 0; $j < 2; ++$j) {
            $sub = self::ZERO;
            $sub[0] = $tmp[0] - 0xffed;
            for ($i = 1; $i < 15; ++$i) {
                $sub[$i] = $tmp[$i] - 0xffff - (($sub[$i - 1] >> 16) & 1);
                $sub[$i - 1] &= 0xffff;
            }
            $sub[15] = $tmp[15] - 0x7fff - (($sub[14] >> 16) & 1);
            $sub[14] &= 0xffff;
            if ((($sub[15] >> 16) & 1) === 0) $tmp = $sub;
        }
        return pack('v16', ...$tmp);
    }

    # The field element of 32 little-endian bytes, the sign bit dropped
    private static function getFieldValue(string $raw): array {
        $num = array_values(unpack('v16', $raw));
        $num[15] &= 0x7fff;
        return $num;
    }

    # The sum of two points in extended coordinates; the formula is complete, so it also doubles
    private static function getPointSum(array $one, array $two): array {
        $aa = self::getFieldProduct(self::getFieldDiff($one[1], $one[0]), self::getFieldDiff($two[1], $two[0]));
        $bb = self::getFieldProduct(self::getFieldSum($one[0], $one[1]), self::getFieldSum($two[0], $two[1]));
        $cc = self::getFieldProduct(self::getFieldProduct($one[3], $two[3]), self::CURVE_DD);
        $dd = self::getFieldProduct($one[2], $two[2]);
        $dd = self::getFieldSum($dd, $dd);
        $ee = self::getFieldDiff($bb, $aa);
        $ff = self::getFieldDiff($dd, $cc);
        $gg = self::getFieldSum($dd, $cc);
        $hh = self::getFieldSum($bb, $aa);
        return [self::getFieldProduct($ee, $ff), self::getFieldProduct($hh, $gg), self::getFieldProduct($gg, $ff), self::getFieldProduct($ee, $hh)];
    }

    # A point times a little-endian scalar by double and add; the inputs are public, so the time it takes may vary
    private static function getPointMultiple(array $pnt, array $num): array {
        $res = [self::ZERO, self::ONE, self::ONE, self::ZERO];
        for ($i = 255; $i >= 0; --$i) {
            $res = self::getPointSum($res, $res);
            if (($num[$i >> 3] >> ($i & 7)) & 1) $res = self::getPointSum($res, $pnt);
        }
        return $res;
    }

    # The 32-byte encoding of a point: y with the parity of x in the top bit
    private static function getPointBytes(array $pnt): string {
        $inv = self::getFieldPower($pnt[2], true);
        $raw = self::getFieldBytes(self::getFieldProduct($pnt[1], $inv));
        $par = ord(self::getFieldBytes(self::getFieldProduct($pnt[0], $inv))[0]) & 1;
        return substr($raw, 0, 31).chr(ord($raw[31]) ^ ($par << 7));
    }

    # The negated point of a canonical public key, or null when the bytes name no point of the curve
    private static function getPointNegated(string $key): ?array {
        $yy = self::getFieldValue($key);
        if (self::getFieldBytes($yy) !== substr($key, 0, 31).chr(ord($key[31]) & 0x7f)) return null;
        $num = self::getFieldProduct($yy, $yy);
        $den = self::getFieldSum(self::ONE, self::getFieldProduct($num, self::CURVE_D));
        $num = self::getFieldDiff($num, self::ONE);
        $dsq = self::getFieldProduct($den, $den);
        $dsix = self::getFieldProduct(self::getFieldProduct($dsq, $dsq), $dsq);
        $tmp = self::getFieldPower(self::getFieldProduct(self::getFieldProduct($dsix, $num), $den), false);
        $xx = self::getFieldProduct(self::getFieldProduct(self::getFieldProduct(self::getFieldProduct($tmp, $num), $den), $den), $den);
        $chk = self::getFieldBytes($num);
        if (self::getFieldBytes(self::getFieldProduct(self::getFieldProduct($xx, $xx), $den)) !== $chk) $xx = self::getFieldProduct($xx, self::ROOT);
        if (self::getFieldBytes(self::getFieldProduct(self::getFieldProduct($xx, $xx), $den)) !== $chk) return null;
        if ((ord(self::getFieldBytes($xx)[0]) & 1) === (ord($key[31]) >> 7)) $xx = self::getFieldDiff(self::ZERO, $xx);
        return [$xx, $yy, self::ONE, self::getFieldProduct($xx, $yy)];
    }

    # A 64-byte little-endian number reduced modulo the group order to 32 bytes
    private static function getScalarReduced(array $num): array {
        for ($i = 63; $i >= 32; --$i) {
            $cy = 0;
            for ($j = $i - 32; $j < $i - 12; ++$j) {
                $num[$j] += $cy - 16 * $num[$i] * self::ORDER[$j - ($i - 32)];
                $cy = ($num[$j] + 128) >> 8;
                $num[$j] -= $cy << 8;
            }
            $num[$j] += $cy;
            $num[$i] = 0;
        }
        $cy = 0;
        for ($j = 0; $j < 32; ++$j) {
            $num[$j] += $cy - ($num[31] >> 4) * self::ORDER[$j];
            $cy = $num[$j] >> 8;
            $num[$j] &= 255;
        }
        for ($j = 0; $j < 32; ++$j) $num[$j] -= $cy * self::ORDER[$j];
        $res = [];
        for ($i = 0; $i < 32; ++$i) {
            $num[$i + 1] += $num[$i] >> 8;
            $res[$i] = $num[$i] & 255;
        }
        return $res;
    }
}
