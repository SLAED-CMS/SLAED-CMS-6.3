<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class sql_db {
    public ?PDO $sqlconnid = null;
    public PDOStatement|false|null $qresult = null;
    public array $qrow = [];
    public array $qrowset = [];
    public int $qnum = 0;
    public float $sqltime = 0;
    public string $qtime = '';
    public ?string $qid = null;
    public ?PDOException $laste = null;

    # Opens connection to the SQL server (PDO)
    public function __construct(string $server, string $user, string $pass, string $dbname, string $charset = 'utf8mb4') {
        $dsn = 'mysql:host='.$server.';dbname='.$dbname.';charset='.$charset;
        try {
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH, PDO::ATTR_EMULATE_PREPARES => false];
            $this->sqlconnid = new PDO($dsn, $user, $pass, $opts);
        } catch (PDOException $e) {
            $msg = _SQLERRORCON.'<br>'._ERROR.': '.$e->getCode().' - '.$e->getMessage();
            setExit($msg);
        }
    }

    # Universal SQL Interpolator. Named (:name) and positional (?) placeholders
    private function sql_interpol(string $query, array $params): string {
        if (empty($params) || !$query) return $query;
        if (preg_match_all('/:[a-zA-Z0-9_]+/', $query, $matches)) {
            $norm = [];
            foreach ($params as $k => $v) {
                $key = (strpos((string)$k, ':') === 0) ? (string)$k : ':'.(string)$k;
                $norm[$key] = $v;
            }
            uksort($norm, function($a, $b) { return strlen($b) <=> strlen($a); });
            foreach ($norm as $ph => $val) {
                $pattern = '/'.preg_quote($ph, '/').'(?![a-zA-Z0-9_])/u';
                $query = preg_replace($pattern, $this->sql_quote($val), $query, 1);
            }
        }
        if (strpos($query, '?') !== false) {
            $vals = array_values($params);
            foreach ($vals as $v) {
                if (strpos($query, '?') === false) break;
                $query = preg_replace('/\?/', $this->sql_quote($v), $query, 1);
            }
        }
        return $query;
    }

    # Quote value for SQL output
    private function sql_quote(mixed $value): string {
        if (is_null($value)) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string)$value;
        if ($this->sqlconnid instanceof PDO) {
            $q = $this->sqlconnid->quote((string)$value);
            return ($q !== false) ? $q : ('\''.str_replace('\'', '\'\'', (string)$value).'\'');
        }
        return '\''.str_replace('\'', '\'\'', (string)$value).'\'';
    }

    # Public wrapper for safe SQL value quoting (used outside this class, e.g. backup)
    public function sql_value(mixed $value): string {
        return $this->sql_quote($value);
    }

    # Closes the connection
    function sql_close(): bool {
        $this->sqlconnid = null;
        return true;
    }

    # Executes SQL query (raw or with parameters)
    # Supports: Named (:name) or Positional (?) placeholders
    function sql_query(string $query = '', array $params = []): PDOStatement|false {
        global $conf, $confs;
        if ($this->qresult) unset($this->qresult);
        if (!$query) return false;
        $this->qid = uniqid('', true);
        $stime = microtime(true);
        $type = 'PDO';
        if (!empty($params)) {
            if (preg_match('/:([a-zA-Z0-9_]+)/', $query)) {
                $type .= ' with :name';
            } elseif (strpos($query, '?') !== false) {
                $type .= ' with ?';
            }
        }
        try {
            if (!empty($params)) {
                $stmt = $this->sqlconnid->prepare($query);
                $stmt->execute($params);
                $this->qresult = $stmt;
            } else {
                $this->qresult = $this->sqlconnid->query($query);
            }
        } catch (PDOException $e) {
            $this->qresult = false;
            $this->laste = $e;
        }
        $ttime = sprintf('%.5f', microtime(true) - $stime);
        $this->sqltime += $ttime;
        $cvar = explode(',', $conf['variables']);
        if ($cvar[8]) {
            $color = ($ttime > 0.01) ? 'sl_red' : 'sl_green';
            $iquery = htmlspecialchars($this->sql_interpol($query, $params));
            $this->qtime .= '<span class="'.$color.'">'.$ttime.'</span> '._SEC.'. - ['.$type.'] - '.$iquery.';';
        }
        if ($this->qresult) {
            if ($cvar[8]) $this->qtime .= '<br>';
            $this->qnum++;
            unset($this->qrow[$this->qid], $this->qrowset[$this->qid]);
            return $this->qresult;
        } else {
            if ($confs['error_log']) {
                $error = $this->sql_error();
                $errmsg = htmlspecialchars($error['message']);
                $errinfo = $error['sqlstate'].' / '.$error['code'];
                $this->qtime .= ' <span class="sl_red">'._ERROR.': '.$errinfo.' - '.$errmsg.'</span><br>';
                if (function_exists('error_sql_log')) {
                    $loginfo = $ttime.' '._SEC.'. - ['.$type.'] - '.$error['sqlstate'].'/'.$error['code'];
                    error_sql_log($loginfo, $error['message'], $this->sql_interpol($query, $params));
                }
            }
            return false;
        }
    }

    # Returns the number of rows (not reliable for SELECT with MySQL)
    function sql_numrows(PDOStatement|int $query_id = 0): int|false {
        if (!$query_id) $query_id = $this->qresult;
        return ($query_id) ? $query_id->rowCount() : false;
    }

    # Returns number of affected rows (INSERT/UPDATE/DELETE)
    function sql_affectedrows(): int|false {
        return ($this->qresult) ? $this->qresult->rowCount() : false;
    }

    # Returns number of columns
    function sql_numfields(PDOStatement|int $query_id = 0): int|false {
        if (!$query_id) $query_id = $this->qresult;
        return ($query_id) ? $query_id->columnCount() : false;
    }

    # Returns column name by offset
    function sql_fieldname(int $offset, PDOStatement|int $query_id = 0): string|false {
        if (!$query_id) $query_id = $this->qresult;
        $meta = $query_id->getColumnMeta($offset);
        return $meta['name'] ?? false;
    }

    # Returns column type
    function sql_fieldtype(int $offset, PDOStatement|int $query_id = 0): string {
        if (!$query_id) $query_id = $this->qresult;
        $meta = $query_id->getColumnMeta($offset);
        return $meta['native_type'] ?? 'unknown';
    }

    # Fetches a single row
    function sql_fetchrow(PDOStatement|int $query_id = 0): array|false {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id) {
            $this->qrow[$this->qid] = $query_id->fetch(PDO::FETCH_BOTH);
            return $this->qrow[$this->qid];
        }
        return false;
    }

    # Fetches all rows
    function sql_fetchrowset(PDOStatement|int $query_id = 0): array|false {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id) {
            $result = $query_id->fetchAll(PDO::FETCH_BOTH);
            $this->qrowset[$this->qid] = $result;
            return $result;
        }
        return false;
    }

    # Fetches a single field from a query result. Safer: avoids unexpected cursor moves
    function sql_fetchfield(string|int $field, int $rownum = 0, PDOStatement|int $query_id = 0): mixed {
        if (!$query_id) $query_id = $this->qresult;
        if (!$query_id) return false;
        if ($rownum === 0 && !empty($this->qrow[$this->qid])) return $this->qrow[$this->qid][$field] ?? false;
        if (!isset($this->qrowset[$this->qid])) $this->qrowset[$this->qid] = $query_id->fetchAll(PDO::FETCH_BOTH);
        $rows = $this->qrowset[$this->qid];
        if ($rownum >= 0 && isset($rows[$rownum][$field])) {
            return $rows[$rownum][$field];
        } elseif (isset($rows[0][$field])) {
            return $rows[0][$field];
        }
        return false;
    }

    # Rowseek (PDO does not support direct seek)
    function sql_rowseek(int $rownum, PDOStatement|int $query_id = 0): bool {
        return false;
    }

    # Last insert ID
    function sql_nextid(): string|false {
        return ($this->sqlconnid) ? $this->sqlconnid->lastInsertId() : false;
    }

    # Free result memory
    function sql_freeresult(PDOStatement|int $query_id = 0): bool {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id && $query_id instanceof PDOStatement) {
            unset($this->qrow[$this->qid], $this->qrowset[$this->qid]);
            $query_id->closeCursor();
            if ($query_id === $this->qresult) $this->qresult = null;
            return true;
        }
        return false;
    }

    # Last error information (robust)
    function sql_error(): array {
        if ($this->laste instanceof PDOException) {
            $ei = $this->laste->errorInfo ?? null;
            if (is_array($ei) && isset($ei[0])) {
                return ['sqlstate' => (string)($ei[0] ?? '00000'), 'code' => (int)($ei[1] ?? 0), 'message' => (string)($ei[2] ?? $this->laste->getMessage())];
            }
            return ['sqlstate' => (string)$this->laste->getCode(), 'code' => 0, 'message' => $this->laste->getMessage()];
        }
        if ($this->sqlconnid instanceof PDO) {
            $ei = $this->sqlconnid->errorInfo();
            return ['sqlstate' => (string)($ei[0] ?? '00000'), 'code' => (int)($ei[1] ?? 0), 'message' => (string)($ei[2] ?? '')];
        }
        return ['sqlstate' => 'HY000', 'code' => 0, 'message' => 'Unknown SQL error'];
    }
}
