<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class sql_db {
    public $sqlconnid;
    public $qresult;
    public $qrow = array();
    public $qrowset = array();
    public $qnum = 0;
    public $sqltime = 0;
    public $qtime = '';
    public $qid;
	public $laste;

    // Open SQL connection
    public function __construct($sqlserver, $sqluser, $sqlpassword, $sqldatabase, $sqlcharset = 'utf8mb4') {

        $this->sqlconnid = new mysqli($sqlserver, $sqluser, $sqlpassword);
        if ($this->sqlconnid->connect_errno || $this->sqlconnid->connect_error) {
            $msg = _SQLERRORCON.'<br>'._ERROR.': '.$this->sqlconnid->connect_errno.' - '.$this->sqlconnid->connect_error;
            setExit($msg);
        } else {
            $this->sqlconnid->set_charset($sqlcharset);
            if ($sqldatabase != '' && !$this->sqlconnid->select_db($sqldatabase)) {
                $msg = _SQLERRORDB.'<br>'._ERROR.': '.$this->sqlconnid->errno.' - '.$this->sqlconnid->error;
                $this->sqlconnid->close();
                $this->sqlconnid = false;
                setExit($msg);
            }
        }
    }
	
	// Universal SQL Interpolator
private function sql_interpol($query, $params) {
    if (empty($params)) return $query;

    // Named placeholders :name (PDO)
    if (preg_match('/:[a-zA-Z0-9_]+/', $query)) {
        foreach ($params as $key => $value) {
            $ph = $key[0] === ':' ? $key : ':' . $key;
            $query = str_replace($ph, $this->sql_quote($value), $query);
        }
    } 
    // Positional placeholders ? (MySQLi)
    elseif (strpos($query, '?') !== false) {
        foreach ($params as $value) {
            $query = preg_replace('/\?/', $this->sql_quote($value), $query, 1);
        }
    }

    return $query;
}

// Quote value for SQL output
private function sql_quote($value) {
    if (is_string($value)) {
        return "'" . ($this->sqlconnid instanceof mysqli
            ? $this->sqlconnid->real_escape_string($value)
            : str_replace("'", "''", $value)) . "'";
    } elseif (is_null($value)) {
        return 'NULL';
    } elseif (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return $value;
}

	/*private function sql_interpol($query, $params) {
    if (empty($params)) return $query;

    foreach ($params as $key => $value) {
        // Prüfe, ob es ein named placeholder ist (:name)
        $placeholder = (strpos($key, ':') === 0) ? $key : ':' . $key;

        if (is_string($value)) {
            $value = "'" . $this->sqlconnid->real_escape_string($value) . "'";
        } elseif (is_null($value)) {
            $value = "NULL";
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $query = str_replace($placeholder, $value, $query);
    }

    return $query;
}*/

    // Close SQL connection
    function sql_close() {
        if ($this->sqlconnid) {
            if ($this->qresult && is_object($this->qresult)) $this->qresult->close();
            return $this->sqlconnid->close();
        }
        return false;
    }

    // Execute SQL query with optional parameters (:name or ?)
    function sql_query($query = '', $params = []) {
        if ($this->qresult) unset($this->qresult);
        if (!$query) return false;

        $this->qid = uniqid();
        $stime = microtime(true);
        $ecode = $emsg = null;
		
		$type = 'MySQLi';
    if (!empty($params)) {
        if (preg_match('/:([a-zA-Z0-9_]+)/', $query)) $type .= ' with :name > ?';
        elseif (strpos($query, '?') !== false) $type .= ' with ?';
    }

        try {
            if (!empty($params)) {
                // Detect named placeholders
                if (preg_match_all('/:([a-zA-Z0-9_]+)/', $query, $matches)) {
                    $placeholders = $matches[0];
                    $types = '';
                    $values = [];

                    foreach ($placeholders as $ph) {
                        if (!array_key_exists($ph, $params)) {
                            throw new Exception("Missing parameter $ph");
                        }
                        $val = $params[$ph];
                        $values[] = $val;
                        $types .= is_int($val) ? 'i' : (is_double($val) ? 'd' : 's');
                    }

                    // Replace :name with ?
                    $query = str_replace($placeholders, array_fill(0, count($placeholders), '?'), $query);

                    $stmt = $this->sqlconnid->prepare($query);
                    if (!$stmt) throw new mysqli_sql_exception($this->sqlconnid->error, $this->sqlconnid->errno);
                    $stmt->bind_param($types, ...$values);
                    $stmt->execute();
                    $this->qresult = $stmt->get_result();
                } else {
                    // ? placeholders
                    $stmt = $this->sqlconnid->prepare($query);
                    if (!$stmt) throw new mysqli_sql_exception($this->sqlconnid->error, $this->sqlconnid->errno);

                    $types = '';
                    $values = [];
                    foreach ($params as $val) {
                        $values[] = $val;
                        $types .= is_int($val) ? 'i' : (is_double($val) ? 'd' : 's');
                    }
                    $stmt->bind_param($types, ...$values);
                    $stmt->execute();
                    $this->qresult = $stmt->get_result();
                }
            } else {
                // Old direct query method
                $this->qresult = $this->sqlconnid->query($query);
            }
        } catch (mysqli_sql_exception $e) {
            $this->qresult = false;
			$this->laste = $e;
            #$ecode = $e->getCode();
            #$emsg = $e->getMessage();
        }

        $ttime = sprintf('%.5f', microtime(true) - $stime);
        $this->sqltime += $ttime;
        $color = ($ttime > 0.01) ? 'sl_red' : 'sl_green';
		
		


        $this->qtime .= '<span class="'.$color.'">'.$ttime.'</span> '._SEC.'. - ['.$type.'] - '.htmlspecialchars($this->sql_interpol($query, $params)).';';
		
		if ($this->qresult) {
    $this->qtime .= '<br>';
    $this->qnum++;
    unset($this->qrow[$this->qid], $this->qrowset[$this->qid]);
    return $this->qresult;
} else {
    $error = $this->sql_error();
    $this->qtime .= ' <span class="sl_red">'._ERROR.': '
        .$error['sqlstate'].' / '.$error['code'].' - '
        .htmlspecialchars($error['message']).'</span><br>';
    if (function_exists('error_sql_log')) {
        error_sql_log($ttime.' '._SEC.'. - ['.$type.'] - '.$error['sqlstate'].'/'.$error['code'], $error['message'], $this->sql_interpol($query, $params));
    }
    return false;
}

/*        if ($this->qresult) {
            $this->qtime .= '<br>';
            $this->qnum += 1;
            unset($this->qrow[$this->qid], $this->qrowset[$this->qid]);
            return $this->qresult;
        } else {
            if ($ecode !== null) {
                $this->qtime .= ' <span class="sl_red">'._ERROR.': '.$ecode.' - '.htmlspecialchars($emsg).'</span><br>';
                if (function_exists('error_sql_log')) error_sql_log($ecode, $emsg, $query);
            } else {
                $this->qtime .= ' <span class="sl_red">'._ERROR.': '.$this->sqlconnid->errno.' - '.htmlspecialchars($this->sqlconnid->error).'</span><br>';
                if (function_exists('error_sql_log')) error_sql_log($this->sqlconnid->errno, $this->sqlconnid->error, $query);
            }
            return false;
        }*/
    }

    // Fetch row as numeric + associative array
    function sql_fetchrow($query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id) {
            $this->qrow[$this->qid] = $query_id->fetch_array(MYSQLI_BOTH);
            return $this->qrow[$this->qid];
        }
        return false;
    }

    // Fetch all rows as numeric + associative arrays
    function sql_fetchrowset($query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id) {
            unset($this->qrowset[$this->qid], $this->qrow[$this->qid]);
            $result = [];
            while ($row = $query_id->fetch_array(MYSQLI_BOTH)) $result[] = $row;
            return $result;
        }
        return false;
    }

    // Fetch single field
    function sql_fetchfield($field, $rownum = -1, $query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        if (!$query_id) return false;

        if ($rownum > -1) {
            $query_id->data_seek($rownum);
            $fetch = $query_id->fetch_array();
            return $fetch[$field] ?? false;
        }

        if (empty($this->qrow[$this->qid]) && empty($this->qrowset[$this->qid])) {
            if ($this->sql_fetchrow()) return $this->qrow[$this->qid][$field] ?? false;
        } else {
            if (!empty($this->qrowset[$this->qid])) return $this->qrowset[$this->qid][0][$field] ?? false;
            elseif (!empty($this->qrow[$this->qid])) return $this->qrow[$this->qid][$field] ?? false;
        }

        return false;
    }

    // Number of rows in result
    function sql_numrows($query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        return ($query_id) ? $query_id->num_rows : false;
    }

    // Number of affected rows in last operation
    function sql_affectedrows() {
        return ($this->sqlconnid) ? $this->sqlconnid->affected_rows : false;
    }

    // Number of fields in result
    function sql_numfields($query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        return ($query_id) ? $query_id->field_count : false;
    }

    // Field name by offset
    function sql_fieldname($offset, $query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        $query_id->field_seek($offset);
        $field = $query_id->fetch_field();
        return ($query_id) ? $field->name : false;
    }

    // Field type by offset
    function sql_fieldtype($offset, $query_id = 0) {
        $types = [
            MYSQLI_TYPE_DECIMAL=>'decimal', MYSQLI_TYPE_NEWDECIMAL=>'numeric', MYSQLI_TYPE_BIT=>'bit', 
            MYSQLI_TYPE_TINY=>'tinyint', MYSQLI_TYPE_SHORT=>'int', MYSQLI_TYPE_LONG=>'int', MYSQLI_TYPE_FLOAT=>'float', 
            MYSQLI_TYPE_DOUBLE=>'double', MYSQLI_TYPE_NULL=>'default null', MYSQLI_TYPE_TIMESTAMP=>'timestamp', 
            MYSQLI_TYPE_LONGLONG=>'bigint', MYSQLI_TYPE_INT24=>'mediumint', MYSQLI_TYPE_DATE=>'date', 
            MYSQLI_TYPE_TIME=>'time', MYSQLI_TYPE_DATETIME=>'datetime', MYSQLI_TYPE_YEAR=>'year', MYSQLI_TYPE_NEWDATE=>'date', 
            MYSQLI_TYPE_ENUM=>'enum', MYSQLI_TYPE_SET=>'set', MYSQLI_TYPE_TINY_BLOB=>'tinyblob', MYSQLI_TYPE_MEDIUM_BLOB=>'mediumblob', 
            MYSQLI_TYPE_LONG_BLOB=>'longblob', MYSQLI_TYPE_BLOB=>'blob', MYSQLI_TYPE_VAR_STRING=>'varchar', 
            MYSQLI_TYPE_STRING=>'char', MYSQLI_TYPE_GEOMETRY=>'geometry'
        ];
        if (!$query_id) $query_id = $this->qresult;
        $query_id->field_seek($offset);
        $field = $query_id->fetch_field();
        return ($query_id) ? $types[$field->type] : false;
    }

    // Seek to row
    function sql_rowseek($rownum, $query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        return ($query_id) ? $query_id->data_seek($rownum) : false;
    }

    // Last insert ID
    function sql_nextid() {
        return ($this->sqlconnid) ? $this->sqlconnid->insert_id : false;
    }

    // Free result memory
    function sql_freeresult($query_id = 0) {
        if (!$query_id) $query_id = $this->qresult;
        if ($query_id) {
            unset($this->qrow[$this->qid], $this->qrowset[$this->qid]);
            $query_id->free_result();
            return true;
        }
        return false;
    }
	
	// Last error information
	function sql_error() {
		if (isset($this->laste) || ($this->sqlconnid)) {
			return ['sqlstate' => $this->sqlconnid->sqlstate ?? $this->laste->getCode(), 'code' => $this->sqlconnid->errno ?? 0, 'message' => $this->sqlconnid->error ?? $this->laste->getMessage()];
		}
		return ['sqlstate' => '00000', 'code' => 0, 'message' => 'no connection'];
	}
}