<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# OAuth2/OIDC client: provider HTTP, JWT validation, one-time flow storage, account links, UI buttons and audit log
class Oauth {

    # Returns the provider config row when OAuth and the provider are active and fully configured, null otherwise
    public static function getProvider(string $prov): ?array {
        global $conf;
        if ((int)($conf['oauth']['active'] ?? 0) !== 1) return null;
        $pcnf = $conf['oauth'][$prov] ?? null;
        if (!is_array($pcnf) || (int)($pcnf['active'] ?? 0) !== 1) return null;
        if (empty($pcnf['clientid']) || empty($pcnf['secret'])) return null;
        return $pcnf;
    }

    # Encodes binary data as base64url without padding as required by PKCE and JWT
    public static function getEncode(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    # Decodes a base64url string back to binary, returns empty string on invalid input
    public static function getDecode(string $enc): string {
        $bin = base64_decode(strtr($enc, '-_', '+/'), true);
        return ($bin === false) ? '' : $bin;
    }

    # Sets or deletes a short-lived OAuth flow cookie; __Host- prefix on HTTPS binds it to host and path, plain name is a dev-only HTTP fallback
    public static function setCookie(string $name, string $val, int $ttl): void {
        global $conf;
        $https = str_starts_with((string)($conf['homeurl'] ?? ''), 'https://');
        $full = ($https ? '__Host-' : '').'oauth-'.$name;
        $opts = ['expires' => ($ttl > 0) ? time() + $ttl : time() - 3600, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
        setcookie($full, $val, $opts);
    }

    # Reads an OAuth flow cookie and returns its value only when it is a valid 64-char hex token
    public static function getCookie(string $name): string {
        global $conf;
        $https = str_starts_with((string)($conf['homeurl'] ?? ''), 'https://');
        $full = ($https ? '__Host-' : '').'oauth-'.$name;
        $val = (string)($_COOKIE[$full] ?? '');
        return preg_match('/^[a-f0-9]{64}$/', $val) ? $val : '';
    }

    # Normalizes a post-login redirect target to a safe internal path, rejects schemes, hosts, backslashes, CRLF and protocol-relative input
    public static function getRedirect(string $raw): string {
        $raw = trim(str_replace(["\r", "\n", '\\'], '', $raw));
        if ($raw === '' || str_contains($raw, '://') || str_starts_with($raw, '//')) return 'index.php';
        $raw = ltrim($raw, '/');
        if ($raw === '' || str_starts_with($raw, 'javascript:') || str_starts_with($raw, 'data:')) return 'index.php';
        return $raw;
    }

    # Performs a hardened curl request to a provider endpoint: TLS verify, no redirects, 10s timeout, 64 KB body cap, JSON content type check
    public static function getHttp(string $url, array $post = [], string $auth = ''): array {
        $curl = curl_init($url);
        $head = ['Accept: application/json'];
        if ($auth !== '') $head[] = 'Authorization: Bearer '.$auth;
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $head,
        ]);
        if ($post) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($curl);
        $err = curl_error($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $ctype = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        if (!is_string($body) || $err !== '') return ['ok' => false, 'code' => $code, 'data' => [], 'error' => ($err !== '') ? $err : 'transport error'];
        if (strlen($body) > 65536) return ['ok' => false, 'code' => $code, 'data' => [], 'error' => 'response too large'];
        if ($code !== 200) return ['ok' => false, 'code' => $code, 'data' => [], 'error' => 'http '.$code];
        if (stripos($ctype, 'application/json') === false) return ['ok' => false, 'code' => $code, 'data' => [], 'error' => 'bad content type'];
        $data = json_decode($body, true);
        if (!is_array($data)) return ['ok' => false, 'code' => $code, 'data' => [], 'error' => 'bad json'];
        return ['ok' => true, 'code' => $code, 'data' => $data, 'error' => ''];
    }

    # Builds the provider authorization URL with PKCE S256 challenge, state, nonce, scopes and optional account chooser prompt
    public static function getAuthUrl(string $prov, string $state, string $verif, string $nonce): string {
        global $conf;
        $pcnf = $conf['oauth'][$prov] ?? [];
        $vals = [
            'response_type' => 'code',
            'client_id' => (string)($pcnf['clientid'] ?? ''),
            'redirect_uri' => $conf['homeurl'].'/index.php?name=account&op=oauth',
            'scope' => (string)($pcnf['scopes'] ?? 'openid email profile'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::getEncode(hash('sha256', $verif, true)),
            'code_challenge_method' => 'S256',
        ];
        if (!empty($pcnf['prompt'])) $vals['prompt'] = $pcnf['prompt'];
        return $pcnf['auth'].'?'.http_build_query($vals);
    }

    # Exchanges the authorization code for tokens at the provider token endpoint using the PKCE verifier
    public static function getTokens(string $prov, string $code, string $verif): array {
        global $conf;
        $pcnf = $conf['oauth'][$prov] ?? [];
        $post = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $conf['homeurl'].'/index.php?name=account&op=oauth',
            'client_id' => (string)($pcnf['clientid'] ?? ''),
            'client_secret' => (string)($pcnf['secret'] ?? ''),
            'code_verifier' => $verif,
        ];
        return self::getHttp((string)($pcnf['token'] ?? ''), $post);
    }

    # Fetches standard OIDC claims from the provider userinfo endpoint, used only when id_token claims are insufficient
    public static function getUserinfo(string $prov, string $tok): array {
        global $conf;
        $data = self::getHttp((string)($conf['oauth'][$prov]['userinfo'] ?? ''), [], $tok);
        return $data['ok'] ? $data['data'] : [];
    }

    # Returns the provider JWKS key list from a 24h file cache; force bypasses the cache, network errors fall back to stale cache
    public static function getJwksKeys(string $prov, bool $force = false): array {
        global $conf;
        $file = CACHE_DIR.'/jwks_'.preg_replace('/[^a-z]/', '', $prov).'.json';
        $stale = [];
        if (is_file($file)) {
            $json = json_decode((string)file_get_contents($file), true);
            if (is_array($json) && isset($json['time'], $json['keys'])) {
                $stale = $json['keys'];
                if (!$force && (time() - (int)$json['time']) < 86400) return $stale;
            }
        }
        $data = self::getHttp((string)($conf['oauth'][$prov]['jwks'] ?? ''));
        if (!$data['ok'] || empty($data['data']['keys']) || !is_array($data['data']['keys'])) {
            if ($stale) {
                self::setLog('jwks_net_error', $prov, 0, $data['error']);
                return $stale;
            }
            throw new RuntimeException('jwks_unavailable');
        }
        $keys = $data['data']['keys'];
        $json = json_encode(['time' => time(), 'keys' => $keys], JSON_UNESCAPED_SLASHES);
        $tmp = $file.'.'.getmypid().'.tmp';
        if (is_string($json) && file_put_contents($tmp, $json, LOCK_EX) !== false) {
            if (is_file($file)) unlink($file);
            rename($tmp, $file);
        }
        return $keys;
    }

    # Builds an RSA public key resource from JWKS modulus and exponent via a minimal DER/ASN.1 encoder
    public static function getRsaKey(array $key): OpenSSLAsymmetricKey|false {
        $mod = self::getDecode((string)($key['n'] ?? ''));
        $exp = self::getDecode((string)($key['e'] ?? ''));
        if ($mod === '' || $exp === '') return false;
        $der = static function (string $type, string $val): string {
            $len = strlen($val);
            if ($len < 128) return $type.chr($len).$val;
            $lstr = ltrim(pack('N', $len), "\0");
            return $type.chr(0x80 | strlen($lstr)).$lstr.$val;
        };
        $int = static function (string $bin) use ($der): string {
            if ($bin === '' || (ord($bin[0]) & 0x80)) $bin = "\0".$bin;
            return $der("\x02", $bin);
        };
        $seq = $der("\x30", $int($mod).$int($exp));
        $bits = $der("\x03", "\0".$seq);
        $algo = $der("\x30", "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der("\x30", $algo.$bits)), 64, "\n").'-----END PUBLIC KEY-----';
        return openssl_pkey_get_public($pem);
    }

    # Validates the Microsoft issuer by substituting tid into the issuer template and enforcing the configured tenant
    public static function checkMsIssuer(string $iss, string $tid, string $itpl, string $ten): void {
        if ($tid === '' || $itpl === '') throw new RuntimeException('ms_tid_mismatch');
        if (!hash_equals(str_replace('{tid}', $tid, $itpl), $iss)) throw new RuntimeException('ms_tid_mismatch');
        if ($ten !== '' && $ten !== 'common' && !hash_equals($ten, $tid)) throw new RuntimeException('ms_tid_mismatch');
    }

    # Decodes and fully validates a provider id_token: structure, RS256 signature against JWKS, exp/nbf/iat with clock skew, iss, aud, azp and nonce
    public static function getJwtPayload(string $tok, string $prov, string $nonce): array {
        global $conf;
        $pcnf = $conf['oauth'][$prov] ?? [];
        $seg = explode('.', $tok);
        if (count($seg) !== 3) throw new RuntimeException('jwt_bad_sig');
        $head = json_decode(self::getDecode($seg[0]), true);
        $pay = json_decode(self::getDecode($seg[1]), true);
        $sig = self::getDecode($seg[2]);
        if (!is_array($head) || !is_array($pay) || $sig === '') throw new RuntimeException('jwt_bad_sig');
        if (($head['alg'] ?? '') !== 'RS256' || empty($head['kid'])) throw new RuntimeException('jwt_bad_sig');
        $kid = (string)$head['kid'];
        $keys = self::getJwksKeys($prov);
        $key = null;
        foreach ($keys as $row) if (($row['kid'] ?? '') === $kid) $key = $row;
        if ($key === null) {
            self::setLog('jwks_kid_miss', $prov);
            foreach (self::getJwksKeys($prov, true) as $row) if (($row['kid'] ?? '') === $kid) $key = $row;
        }
        if ($key === null) throw new RuntimeException('jwks_kid_miss');
        $rsa = self::getRsaKey($key);
        if ($rsa === false || openssl_verify($seg[0].'.'.$seg[1], $sig, $rsa, OPENSSL_ALGO_SHA256) !== 1) throw new RuntimeException('jwt_bad_sig');
        $now = time();
        $skew = 60;
        if ((int)($pay['exp'] ?? 0) < $now - $skew) throw new RuntimeException('jwt_expired');
        if (isset($pay['nbf']) && (int)$pay['nbf'] > $now + $skew) throw new RuntimeException('jwt_not_yet');
        if (!isset($pay['iat']) || (int)$pay['iat'] > $now + $skew) throw new RuntimeException('jwt_bad_iat');
        $iss = (string)($pay['iss'] ?? '');
        $itpl = (string)($pcnf['isstpl'] ?? '');
        if ($itpl !== '') {
            self::checkMsIssuer($iss, (string)($pay['tid'] ?? ''), $itpl, (string)($pcnf['tenant'] ?? ''));
        } else {
            $list = array_filter(array_map('trim', explode(',', (string)($pcnf['iss'] ?? ''))));
            if (!in_array($iss, $list, true)) throw new RuntimeException('jwt_bad_iss');
        }
        $aud = $pay['aud'] ?? '';
        $cid = (string)($pcnf['clientid'] ?? '');
        if (is_array($aud) ? !in_array($cid, $aud, true) : $aud !== $cid) throw new RuntimeException('jwt_bad_aud');
        if (isset($pay['azp']) && (string)$pay['azp'] !== $cid) throw new RuntimeException('jwt_bad_azp');
        if (!isset($pay['nonce']) || !hash_equals($nonce, (string)$pay['nonce'])) throw new RuntimeException('jwt_bad_nonce');
        return $pay;
    }

    # Maps provider claims to a normalized array (sub, email, verified, name); Microsoft email counts as verified only with xms_edov or a consumer-tenant account
    public static function getClaims(array $pay, string $prov): array {
        $sub = (string)($pay['sub'] ?? '');
        $mail = (string)($pay['email'] ?? ($pay['mail'] ?? ($pay['userPrincipalName'] ?? '')));
        $uname = (string)($pay['name'] ?? ($pay['displayName'] ?? ''));
        if ($uname === '' && $mail !== '') $uname = strstr($mail, '@', true) ?: '';
        if ($prov === 'microsoft') {
            $msa = ((string)($pay['tid'] ?? '')) === '9188040d-6c67-4c5b-b112-36a304b66dad';
            $ver = $mail !== '' && (!empty($pay['xms_edov']) || $msa);
        } else {
            $ver = $mail !== '' && !empty($pay['email_verified']);
        }
        return ['sub' => $sub, 'email' => $mail, 'verified' => $ver, 'name' => $uname];
    }

    # Sanitizes external claims before storage and rendering: trims, enforces lengths, strips control chars and validates the email format
    public static function filterClaims(array $claims): array {
        $sub = trim(substr((string)($claims['sub'] ?? ''), 0, 255));
        $mail = mb_strtolower(trim(substr((string)($claims['email'] ?? ''), 0, 255)));
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) $mail = '';
        $uname = preg_replace('/[\x00-\x1F\x7F]/', '', (string)($claims['name'] ?? ''));
        $uname = trim(preg_replace('/\s+/', ' ', (string)$uname));
        $uname = mb_substr($uname, 0, 128);
        return ['sub' => $sub, 'email' => $mail, 'verified' => ($mail !== '') && !empty($claims['verified']), 'name' => $uname];
    }

    # Stores a one-time flow record (kind state or pending) and removes expired rows of that kind in the same call
    public static function setTemp(string $kind, string $tok, array $data): bool {
        global $db;
        $ttl = ($kind === 'state') ? 600 : 900;
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_oauth_temp WHERE kind = :kind AND time < :old', ['kind' => $kind, 'old' => time() - $ttl]);
        $res = $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_oauth_temp (token, kind, provider, nonce, verifier, uid, email, uname, redirect, time) VALUES (:tok, :kind, :prov, :nonce, :verif, :uid, :email, :uname, :redir, :time)',
            [
                'tok' => $tok,
                'kind' => $kind,
                'prov' => (string)($data['provider'] ?? ''),
                'nonce' => (string)($data['nonce'] ?? ''),
                'verif' => (string)($data['verifier'] ?? ''),
                'uid' => (string)($data['uid'] ?? ''),
                'email' => (string)($data['email'] ?? ''),
                'uname' => (string)($data['uname'] ?? ''),
                'redir' => (string)($data['redirect'] ?? ''),
                'time' => time(),
            ]
        );
        return $res !== false;
    }

    # Returns a one-time flow record when it exists, matches the kind and is not expired, null otherwise
    public static function getTemp(string $kind, string $tok): ?array {
        global $db;
        if ($tok === '') return null;
        $ttl = ($kind === 'state') ? 600 : 900;
        $res = $db->getSqlQuery('SELECT token, kind, provider, nonce, verifier, uid, email, uname, redirect, time FROM '.PREFIX_DB.'_oauth_temp WHERE token = :tok AND kind = :kind', ['tok' => $tok, 'kind' => $kind]);
        $row = ($res) ? $db->getSqlRow($res) : null;
        if (!is_array($row) || !isset($row['token'])) return null;
        if ((int)$row['time'] < time() - $ttl) {
            self::deleteTemp($tok);
            return null;
        }
        return $row;
    }

    # Deletes a one-time flow record immediately after use
    public static function deleteTemp(string $tok): void {
        global $db;
        if ($tok !== '') $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_oauth_temp WHERE token = :tok', ['tok' => $tok]);
    }

    # Finds the linked SLAED user id for a provider identity, null when no link exists
    public static function getUserId(string $prov, string $puid): ?int {
        global $db;
        if ($prov === '' || $puid === '') return null;
        $res = $db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.'_user_oauth WHERE provider = :prov AND puid = :puid', ['prov' => $prov, 'puid' => $puid]);
        $row = ($res) ? $db->getSqlRow($res) : null;
        return (is_array($row) && !empty($row['uid'])) ? (int)$row['uid'] : null;
    }

    # Returns all provider links of a SLAED user ordered by provider name
    public static function getLinks(int $uid): array {
        global $db;
        $rows = [];
        $res = $db->getSqlQuery('SELECT provider, puid, email, linked, lastlog FROM '.PREFIX_DB.'_user_oauth WHERE uid = :uid ORDER BY provider ASC', ['uid' => $uid]);
        while ($res && ($row = $db->getSqlRow($res))) $rows[] = $row;
        return $rows;
    }

    # Links a provider identity to a SLAED user; returns empty string on success or a domain error code (link_duplicate on unique key conflicts)
    public static function setLink(int $uid, string $prov, string $puid, string $mail): string {
        global $db;
        $res = $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_user_oauth (uid, provider, puid, email, linked, lastlog) VALUES (:uid, :prov, :puid, :email, :time, :time2)',
            ['uid' => $uid, 'prov' => $prov, 'puid' => $puid, 'email' => $mail, 'time' => time(), 'time2' => time()]
        );
        if ($res !== false) return '';
        $err = $db->getSqlError();
        return ((int)($err['code'] ?? 0) === 1062) ? 'link_duplicate' : 'link_failed';
    }

    # Unlinks a provider from a SLAED user; refuses when neither a usable password nor another provider link would remain
    public static function deleteLink(int $uid, string $prov): string {
        global $db;
        $res = $db->getSqlQuery('SELECT password FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]);
        $row = ($res) ? $db->getSqlRow($res) : null;
        $pass = is_array($row) ? (string)($row['password'] ?? '') : '';
        $haspw = $pass !== '' && !str_starts_with($pass, '!');
        $links = self::getLinks($uid);
        $other = 0;
        foreach ($links as $row) if (($row['provider'] ?? '') !== $prov) $other++;
        if (!$haspw && $other === 0) return 'unlink_last_method';
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_user_oauth WHERE uid = :uid AND provider = :prov', ['uid' => $uid, 'prov' => $prov]);
        return '';
    }

    # Renders sign-in buttons for all ready OAuth providers, empty string when OAuth is disabled or no provider is fully configured
    public static function getButtons(string $redir = ''): string {
        global $tpl;
        $rows = [];
        foreach (['google', 'microsoft'] as $prov) {
            if (!self::getProvider($prov)) continue;
            $url = 'index.php?name=account&op=oauth_init&prov='.$prov;
            if ($redir !== '') $url .= '&redirect='.rawurlencode($redir);
            $rows[] = ['prov' => $prov, 'label' => ucfirst($prov), 'href' => $url];
        }
        if (!$rows) return '';
        return $tpl->getHtmlFrag('oauth-buttons', ['providers' => $rows, 'title' => _OAUTHWITH]);
    }

    # Appends an OAuth audit event to storage/logs/log_oauth.log with size-capped rotation; never logs tokens, codes or raw claims
    public static function setLog(string $event, string $prov = '', int $uid = 0, string $note = '', int $actor = 0): void {
        global $conf;
        $file = LOGS_DIR.'/log_oauth.log';
        $note = substr(preg_replace('/[\r\n]+/', ' ', $note), 0, 200);
        $line = date('Y-m-d H:i:s').' | '.$event.' | '.$prov.' | uid='.$uid.(($actor) ? ' | actor='.$actor : '').' | ip='.getIp().(($note !== '') ? ' | '.$note : '')."\n";
        $mode = (is_file($file) && filesize($file) > (int)($conf['security']['log_size'] ?? 262144)) ? 'wb' : 'ab';
        if ($fh = fopen($file, $mode)) {
            fwrite($fh, $line);
            fclose($fh);
        }
    }
}
