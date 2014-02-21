<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XP_DB_mysql_driver extends XP_DB
{
    public $dbdriver = 'mysql';
    public $_escape_char = '`';
    public $_like_escape_str = '';
    public $_like_escape_chr = '';
    public $delete_hack = TRUE;
    public $_count_string = 'SELECT COUNT(*) AS ';
    public $_random_keyword = ' RAND()';
    public $use_set_names;
    public function db_connect()
    {
        if ($this->port != '') {
            $this->hostname .= ':' . $this->port;
        }
        return @mysql_connect($this->hostname, $this->username, $this->password, TRUE);
    }
    public function db_pconnect()
    {
        if ($this->port != '') {
            $this->hostname .= ':' . $this->port;
        }
        return @mysql_pconnect($this->hostname, $this->username, $this->password);
    }
    public function reconnect()
    {
        if (mysql_ping($this->conn_id) === FALSE) {
            $this->conn_id = FALSE;
        }
    }
    public function db_select()
    {
        return @mysql_select_db($this->database, $this->conn_id);
    }
    public function _db_set_charset($charset, $collation)
    {
        if (!isset($this->use_set_names)) {
            $this->use_set_names = version_compare(PHP_VERSION, '5.2.3', '>=') && version_compare(mysql_get_server_info(), '5.0.7', '>=') ? FALSE : TRUE;
        }
        if ($this->use_set_names === TRUE) {
            return @mysql_query((((('SET NAMES \'' . $this->escape_str($charset)) . '\' COLLATE \'') . $this->escape_str($collation)) . '\''), $this->conn_id);
        } else {
            return @mysql_set_charset($charset, $this->conn_id);
        }
    }
    public function _version()
    {
        return 'SELECT version() AS ver';
    }
    public function _execute($sql)
    {
        $sql = $this->_prep_query($sql);
        return @mysql_query($sql, $this->conn_id);
    }
    public function _prep_query($sql)
    {
        if ($this->delete_hack === TRUE) {
            if (preg_match('/^\\s*DELETE\\s+FROM\\s+(\\S+)\\s*$/i', $sql)) {
                $sql = preg_replace('/^\\s*DELETE\\s+FROM\\s+(\\S+)\\s*$/', 'DELETE FROM \\1 WHERE 1=1', $sql);
            }
        }
        return $sql;
    }
    public function trans_begin($test_mode = FALSE)
    {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->_trans_failure = $test_mode === TRUE ? TRUE : FALSE;
        $this->simple_query('SET AUTOCOMMIT=0');
        $this->simple_query('START TRANSACTION');
        return TRUE;
    }
    public function trans_commit()
    {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('COMMIT');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }
    public function trans_rollback()
    {
        if (!$this->trans_enabled) {
            return TRUE;
        }
        if ($this->_trans_depth > 0) {
            return TRUE;
        }
        $this->simple_query('ROLLBACK');
        $this->simple_query('SET AUTOCOMMIT=1');
        return TRUE;
    }
    public function escape_str($str, $like = FALSE)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escape_str($val, $like);
            }
            return $str;
        }
        if (function_exists('mysql_real_escape_string') and is_resource($this->conn_id)) {
            $str = mysql_real_escape_string($str, $this->conn_id);
        } else {
            $str = addslashes($str);
        }
        if ($like === TRUE) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }
        return $str;
    }
    public function affected_rows()
    {
        return @mysql_affected_rows($this->conn_id);
    }
    public function insert_id()
    {
        return @mysql_insert_id($this->conn_id);
    }
    public function count_all($table = '',$id = NULL)
    {
        if ($table == '') {
            return 0;
        }
        if( !empty($id) ) $this->_count_string = str_replace ('*', $id, $this->_count_string);
        $query = $this->query((($this->_count_string . $this->_protect_identifiers('numrows')) . ' FROM ') . $this->_protect_identifiers($table, TRUE, NULL, FALSE));
        if ($query->num_rows() == 0) {
            return 0;
        }
        $row = $query->row();
        $this->_reset_select();
        return (int) $row->numrows;
    }
    public function _list_tables($prefix_limit = FALSE)
    {
        $sql = (('SHOW TABLES FROM ' . $this->_escape_char) . $this->database) . $this->_escape_char;
        if ($prefix_limit !== FALSE and $this->dbprefix != '') {
            $sql .= (' LIKE \'' . $this->escape_like_str($this->dbprefix)) . '%\'';
        }
        return $sql;
    }
    public function _list_columns($table = '')
    {
        return 'SHOW COLUMNS FROM ' . $this->_protect_identifiers($table, TRUE, NULL, FALSE);
    }
    public function _field_data($table)
    {
        return 'DESCRIBE ' . $table;
    }
    public function _error_message()
    {
        return mysql_error($this->conn_id);
    }
    public function _error_number()
    {
        return mysql_errno($this->conn_id);
    }
    public function _escape_identifiers($item)
    {
        if ($this->_escape_char == '') {
            return $item;
        }
        foreach ($this->_reserved_identifiers as $id) {
            if (strpos($item, '.' . $id) !== FALSE) {
                $str = $this->_escape_char . str_replace('.', ($this->_escape_char . '.'), $item);
                return preg_replace(('/[' . $this->_escape_char) . ']+/', $this->_escape_char, $str);
            }
        }
        if (strpos($item, '.') !== FALSE) {
            $str = ($this->_escape_char . str_replace('.', (($this->_escape_char . '.') . $this->_escape_char), $item)) . $this->_escape_char;
        } else {
            $str = ($this->_escape_char . $item) . $this->_escape_char;
        }
        return preg_replace(('/[' . $this->_escape_char) . ']+/', $this->_escape_char, $str);
    }
    public function _from_tables($tables)
    {
        if (!is_array($tables)) {
            $tables = array($tables);
        }
        return ('(' . implode(', ', $tables)) . ')';
    }
    public function _insert($table, $keys, $values)
    {
        return ((((('INSERT INTO ' . $table) . ' (') . implode(', ', $keys)) . ') VALUES (') . implode(', ', $values)) . ')';
    }
    public function _replace($table, $keys, $values)
    {
        return ((((('REPLACE INTO ' . $table) . ' (') . implode(', ', $keys)) . ') VALUES (') . implode(', ', $values)) . ')';
    }
    public function _insert_batch($table, $keys, $values)
    {
        return (((('INSERT INTO ' . $table) . ' (') . implode(', ', $keys)) . ') VALUES ') . implode(', ', $values);
    }
    public function _update($table, $values, $where, $orderby = array(), $limit = FALSE)
    {
        foreach ($values as $key => $val) {
            $valstr[] = ($key . ' = ') . $val;
        }
        $limit = !$limit ? '' : ' LIMIT ' . $limit;
        $orderby = count($orderby) >= 1 ? ' ORDER BY ' . implode(', ', $orderby) : '';
        $sql = (('UPDATE ' . $table) . ' SET ') . implode(', ', $valstr);
        $sql .= ($where != '' and count($where) >= 1) ? ' WHERE ' . implode(' ', $where) : '';
        $sql .= $orderby . $limit;
        return $sql;
    }
    public function _update_batch($table, $values, $index, $where = NULL)
    {
        $ids = array();
        $where = ($where != '' and count($where) >= 1) ? implode(' ', $where) . ' AND ' : '';
        foreach ($values as $key => $val) {
            $ids[] = $val[$index];
            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = (((('WHEN ' . $index) . ' = ') . $val[$index]) . ' THEN ') . $val[$field];
                }
            }
        }
        $sql = ('UPDATE ' . $table) . ' SET ';
        $cases = '';
        foreach ($final as $k => $v) {
            $cases .= ($k . ' = CASE ') . "\n";
            foreach ($v as $row) {
                $cases .= $row . "\n";
            }
            $cases .= ('ELSE ' . $k) . ' END, ';
        }
        $sql .= substr($cases, 0, -2);
        $sql .= ((((' WHERE ' . $where) . $index) . ' IN (') . implode(',', $ids)) . ')';
        return $sql;
    }
    public function _truncate($table)
    {
        return 'TRUNCATE ' . $table;
    }
    public function _delete($table, $where = array(), $like = array(), $limit = FALSE)
    {
        $conditions = '';
        if (count($where) > 0 or count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);
            if (count($where) > 0 && count($like) > 0) {
                $conditions .= ' AND ';
            }
            $conditions .= implode("\n", $like);
        }
        $limit = !$limit ? '' : ' LIMIT ' . $limit;
        return (('DELETE FROM ' . $table) . $conditions) . $limit;
    }
    public function _limit($sql, $limit, $offset)
    {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ', ';
        }
        return (($sql . 'LIMIT ') . $offset) . $limit;
    }
    public function _close($conn_id)
    {
        @mysql_close($conn_id);
    }
}
class XP_DB_mysql_result extends XP_DB_result
{
    public function num_rows()
    {
        return @mysql_num_rows($this->result_id);
    }
    public function num_fields()
    {
        return @mysql_num_fields($this->result_id);
    }
    public function list_fields()
    {
        $field_names = array();
        while ($field = mysql_fetch_field($this->result_id)) {
            $field_names[] = $field->name;
        }
        return $field_names;
    }
    public function field_data()
    {
        $retval = array();
        while ($field = mysql_fetch_object($this->result_id)) {
            preg_match('/([a-zA-Z]+)(\\(\\d+\\))?/', $field->Type, $matches);
            $type = array_key_exists(1, $matches) ? $matches[1] : NULL;
            $length = array_key_exists(2, $matches) ? preg_replace('/[^\\d]/', '', $matches[2]) : NULL;
            $F = new stdClass();
            $F->name = $field->Field;
            $F->type = $type;
            $F->default = $field->Default;
            $F->max_length = $length;
            $F->primary_key = $field->Key == 'PRI' ? 1 : 0;
            $retval[] = $F;
        }
        return $retval;
    }
    public function free_result()
    {
        if (is_resource($this->result_id)) {
            mysql_free_result($this->result_id);
            $this->result_id = FALSE;
        }
    }
    public function _data_seek($n = 0)
    {
        return mysql_data_seek($this->result_id, $n);
    }
    public function _fetch_assoc()
    {
        return mysql_fetch_assoc($this->result_id);
    }
    public function _fetch_object()
    {
        return mysql_fetch_object($this->result_id);
    }
}
