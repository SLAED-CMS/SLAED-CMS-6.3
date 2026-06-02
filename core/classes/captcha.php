<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Provider contract: every captcha backend renders a widget, verifies a solution, and serves a challenge
interface CaptchaProvider {
    public function html(string $act): string;
    public function check(string $act): bool;
    public function challenge(string $act): void;
}

# Disabled provider: renders nothing and never blocks
class NullCaptchaProvider implements CaptchaProvider {
    public function html(string $act): string { return ''; }
    public function check(string $act): bool { return false; }
    public function challenge(string $act): void {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'disabled'], JSON_UNESCAPED_SLASHES);
    }
}

# Self-hosted ALTCHA provider: proof-of-work challenge with HMAC signature, verified without external services
class AltchaCaptchaProvider implements CaptchaProvider {
    private const ALGO = 'SHA-256';
    private const FIELD = 'altcha';

    # Render the widget bundle for the given action
    public function html(string $act): string {
        global $tpl;
        $url = 'index.php?go=captcha&act='.rawurlencode($act);
        return $tpl->getHtmlFrag('captcha-altcha', [
            'script_attr'   => 'type="module" async defer',
            'script_src'    => 'plugins/altcha/altcha.min.js',
            'challengeurl'  => $url,
            'field_name'    => self::FIELD,
            'strings_attr'  => $this->strings(),
            'hp_name'       => Captcha::honeypotField(),
            'ts_name'       => Captcha::timeField(),
            'ts_value'      => Captcha::timeToken(),
        ]);
    }

    # Verify the posted ALTCHA payload; return true when the request must be blocked
    public function check(string $act): bool {
        $payload = getVar('post', self::FIELD, 'raw', '');
        if (!is_string($payload) || $payload === '') return true;
        $raw = base64_decode($payload, true);
        if ($raw === false) return true;
        $data = json_decode($raw, true);
        if (!is_array($data)) return true;
        foreach (['algorithm', 'challenge', 'number', 'salt', 'signature'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) return true;
        }
        if ((string)$data['algorithm'] !== self::ALGO) return true;
        $salt = (string)$data['salt'];
        $challenge = (string)$data['challenge'];
        $signature = (string)$data['signature'];
        $expected = hash('sha256', $salt.(string)$data['number']);
        if (!hash_equals($expected, $challenge)) return true;
        $secret = CaptchaStore::secret();
        if ($secret === '') return true;
        if (!hash_equals(hash_hmac('sha256', $challenge, $secret), $signature)) return true;
        $expires = $this->saltExpires($salt);
        if ($expires > 0 && time() > $expires) return true;
        # Replay protection: a signed solution is accepted exactly once until it expires anyway
        $id = hash('sha256', $signature);
        if (CaptchaStore::isUsed($id)) return true;
        CaptchaStore::markUsed($id, $expires > 0 ? $expires : time() + Captcha::ttl());
        return false;
    }

    # Emit a fresh signed challenge as JSON for the widget to solve
    public function challenge(string $act): void {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $secret = CaptchaStore::secret();
        if ($secret === '') {
            http_response_code(503);
            echo json_encode(['error' => 'storage'], JSON_UNESCAPED_SLASHES);
            return;
        }
        $max = Captcha::maxNumber();
        $expires = time() + Captcha::ttl();
        $salt = bin2hex(random_bytes(12)).'?expires='.$expires;
        $number = random_int(0, $max);
        $challenge = hash('sha256', $salt.$number);
        echo json_encode([
            'algorithm' => self::ALGO,
            'challenge' => $challenge,
            'maxnumber' => $max,
            'salt'      => $salt,
            'signature' => hash_hmac('sha256', $challenge, $secret),
        ], JSON_UNESCAPED_SLASHES);
    }

    # Extract the expiry timestamp embedded in the signed salt, if any
    private function saltExpires(string $salt): int {
        $pos = strpos($salt, '?');
        if ($pos === false) return 0;
        parse_str(substr($salt, $pos + 1), $params);
        return isset($params['expires']) ? (int)$params['expires'] : 0;
    }

    # Build the localized widget strings as a JSON attribute value
    private function strings(): string {
        $map = [
            'label'     => defined('_CAPTCHA_LABEL') ? _CAPTCHA_LABEL : 'I am human',
            'verifying' => defined('_CAPTCHA_VERIFYING') ? _CAPTCHA_VERIFYING : 'Verifying...',
            'verified'  => defined('_CAPTCHA_VERIFIED') ? _CAPTCHA_VERIFIED : 'Verified',
            'error'     => defined('_CAPTCHA_ERROR') ? _CAPTCHA_ERROR : 'Verification failed',
            'expired'   => defined('_CAPTCHA_EXPIRED') ? _CAPTCHA_EXPIRED : 'Verification expired',
        ];
        return (string)json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

# File-based store: secret, one-time replay markers and rolling counters under storage/captcha
class CaptchaStore {
    # Ensure the storage directory exists and is protected from direct web access
    public static function ensureDir(): bool {
        if (!is_dir(CAPTCHA_DIR) && !mkdir(CAPTCHA_DIR, 0750, true) && !is_dir(CAPTCHA_DIR)) return false;
        $guard = CAPTCHA_DIR.'/.htaccess';
        if (!is_file($guard)) self::write($guard, 'deny from all');
        $index = CAPTCHA_DIR.'/index.html';
        if (!is_file($index)) self::write($index, '<!DOCTYPE html><html><head><meta charset="utf-8"><title>SLAED CMS</title><meta http-equiv="refresh" content="0; url=https://slaed.net"></head><body></body></html>');
        return is_writable(CAPTCHA_DIR);
    }

    # Read a small store file, returning '' when missing or unreadable
    private static function read(string $file): string {
        if (!is_file($file)) return '';
        $data = file_get_contents($file);
        return $data === false ? '' : $data;
    }

    # Report whether the store can be written, for admin diagnostics
    public static function isWritable(): bool {
        return self::ensureDir();
    }

    # Return the captcha HMAC key, derived from the site master secret
    public static function secret(): string {
        return getSecret('captcha');
    }

    # Mark a solution id as used until the given expiry timestamp
    public static function markUsed(string $id, int $expires): bool {
        if (!self::ensureDir()) return false;
        self::maybeCleanup();
        return self::write(self::usedFile($id), (string)$expires);
    }

    # Report whether a solution id was already used and is still within its lifetime
    public static function isUsed(string $id): bool {
        $file = self::usedFile($id);
        if (!is_file($file)) return false;
        $expires = (int)self::read($file);
        if ($expires > 0 && time() > $expires) {
            unlink($file);
            return false;
        }
        return true;
    }

    # Increment a rolling counter within a time window and return the current count
    public static function increment(string $key, int $window): int {
        if (!self::ensureDir()) return 0;
        $file = self::counterFile($key);
        $now = time();
        $handle = fopen($file, 'c+');
        if ($handle === false) return 0;
        $count = 0;
        if (flock($handle, LOCK_EX)) {
            $body = stream_get_contents($handle);
            $data = $body !== false && $body !== '' ? json_decode($body, true) : null;
            if (is_array($data) && isset($data['reset']) && (int)$data['reset'] > $now) {
                $count = (int)($data['count'] ?? 0) + 1;
                $reset = (int)$data['reset'];
            } else {
                $count = 1;
                $reset = $now + $window;
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string)json_encode(['count' => $count, 'reset' => $reset]));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        return $count;
    }

    # Read the current counter value for a key, ignoring expired windows
    public static function counter(string $key): int {
        $file = self::counterFile($key);
        if (!is_file($file)) return 0;
        $data = json_decode(self::read($file), true);
        if (!is_array($data) || (int)($data['reset'] ?? 0) <= time()) return 0;
        return (int)($data['count'] ?? 0);
    }

    # Drop a counter, e.g. after a successful login clears the failure streak
    public static function clear(string $key): void {
        $file = self::counterFile($key);
        if (is_file($file)) unlink($file);
    }

    # Build the path for a one-time replay marker
    private static function usedFile(string $id): string {
        return CAPTCHA_DIR.'/'.hash('sha256', $id).'.txt';
    }

    # Build the path for a rolling counter file
    private static function counterFile(string $key): string {
        return CAPTCHA_DIR.'/'.hash('sha256', $key).'.json';
    }

    # Atomically write content via a temporary file and rename
    private static function write(string $file, string $content): bool {
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) return false;
        if (!rename($tmp, $file)) {
            unlink($tmp);
            return false;
        }
        return true;
    }

    # Occasionally sweep expired replay markers to keep the store small
    private static function maybeCleanup(): void {
        if (random_int(1, 100) !== 1) return;
        $now = time();
        foreach (glob(CAPTCHA_DIR.'/*.txt') ?: [] as $file) {
            $expires = (int)self::read($file);
            if ($expires > 0 && $now > $expires) unlink($file);
        }
        foreach (glob(CAPTCHA_DIR.'/*.json') ?: [] as $file) {
            $data = json_decode(self::read($file), true);
            if (is_array($data) && (int)($data['reset'] ?? 0) <= $now) unlink($file);
        }
    }
}

# Captcha facade: resolves configuration, applies cross-cutting protection and delegates to the active provider
class Captcha {
    private static ?CaptchaProvider $provider = null;

    # Map an action to its configuration toggle
    private static function flag(string $act): string {
        return match ($act) {
            'register'   => 'register',
            'contact'    => 'contact',
            'login'      => 'login_user',
            'adminlogin' => 'login_admin',
            default      => 'comments',
        };
    }

    # Resolve the captcha configuration with safe defaults
    private static function conf(): array {
        global $conf;
        return is_array($conf['security']['captcha'] ?? null) ? $conf['security']['captcha'] : [];
    }

    # Lazily build the active provider from configuration
    private static function provider(): CaptchaProvider {
        if (self::$provider !== null) return self::$provider;
        $cfg = self::conf();
        $active = !empty($cfg['active']);
        $name = (string)($cfg['provider'] ?? 'altcha');
        if ($active && $name === 'altcha') return self::$provider = new AltchaCaptchaProvider();
        return self::$provider = new NullCaptchaProvider();
    }

    # Decide whether captcha is required for the given action right now
    public static function isActive(string $act): bool {
        $cfg = self::conf();
        if (empty($cfg['active']) || (string)($cfg['provider'] ?? 'altcha') === 'null') return false;
        $flag = self::flag($act);
        if ($flag === 'login_user' || $flag === 'login_admin') {
            $mode = (string)($cfg[$flag] ?? 'never');
            if ($mode === 'always') return true;
            if ($mode === 'never') return false;
            return self::loginFailures($flag === 'login_admin' ? 'admin' : 'user') >= self::failThreshold();
        }
        # Content forms only challenge guests, matching long-standing behavior
        if (function_exists('is_user') && is_user()) return false;
        return !empty($cfg[$flag]);
    }

    # Render the full captcha block (widget, honeypot, timing token) for a form
    public static function html(string $act): string {
        if (!self::isActive($act)) return '';
        return self::provider()->html($act);
    }

    # Verify a submission; return true when the request must be blocked
    public static function check(string $act): bool {
        if (!self::isActive($act)) return false;
        if (self::rateLimited()) return true;
        if (self::honeypotTripped()) return true;
        if (!self::timeTokenValid()) return true;
        $blocked = self::provider()->check($act);
        if ($blocked) self::increment('rl:'.getIp(), self::ttl());
        return $blocked;
    }

    # Serve a challenge for the given action
    public static function challenge(string $act): void {
        self::provider()->challenge($act);
    }

    # --- Login failure tracking feeds the "after failed attempts" mode ---

    # Record a failed login for the given scope (user|admin)
    public static function registerLoginFailure(string $scope): void {
        self::increment('lf:'.$scope.':'.getIp(), self::loginWindow());
    }

    # Clear the failure streak after a successful login
    public static function clearLoginFailures(string $scope): void {
        CaptchaStore::clear('lf:'.$scope.':'.getIp());
    }

    # Current failure count for a scope
    private static function loginFailures(string $scope): int {
        return CaptchaStore::counter('lf:'.$scope.':'.getIp());
    }

    # --- Cross-cutting helpers ---

    private static function honeypotTripped(): bool {
        return getVar('post', self::honeypotField(), 'text', '') !== '';
    }

    private static function timeTokenValid(): bool {
        $token = getVar('post', self::timeField(), 'raw', '');
        if (!is_string($token) || !str_contains($token, '.')) return false;
        [$issued, $sig] = explode('.', $token, 2);
        $issued = (int)$issued;
        $secret = CaptchaStore::secret();
        if ($secret === '' || !hash_equals(hash_hmac('sha256', (string)$issued, $secret), $sig)) return false;
        $age = time() - $issued;
        return $age >= self::minSeconds() && $age <= self::ttl();
    }

    private static function rateLimited(): bool {
        return CaptchaStore::counter('rl:'.getIp()) >= self::rateMax();
    }

    private static function increment(string $key, int $window): void {
        CaptchaStore::increment($key, $window);
    }

    # --- Token builders exposed to the provider ---

    public static function honeypotField(): string { return 'sl_url'; }
    public static function timeField(): string { return 'sl_ct'; }

    public static function timeToken(): string {
        $issued = time();
        $secret = CaptchaStore::secret();
        return $issued.'.'.hash_hmac('sha256', (string)$issued, $secret);
    }

    # --- Tunables derived from configuration ---

    public static function ttl(): int {
        $ttl = (int)(self::conf()['ttl'] ?? 600);
        return $ttl > 0 ? $ttl : 600;
    }

    public static function maxNumber(): int {
        return match ((string)(self::conf()['difficulty'] ?? 'normal')) {
            'low'  => 30000,
            'high' => 300000,
            default => 100000,
        };
    }

    private static function minSeconds(): int { return 2; }
    private static function failThreshold(): int { return 3; }
    private static function loginWindow(): int { return 900; }
    private static function rateMax(): int { return 30; }

    # Storage diagnostics for the admin panel
    public static function isStoreWritable(): bool { return CaptchaStore::isWritable(); }
}
