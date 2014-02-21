<?php if ( !defined('IN_XP_APP') ) exit('No direct script access allowed');

class XpDB
{
    private static $conns = array();

    public static function getInstance($config)
    {
        $class = ('XP_DB_' . $config['dbdriver']) . '_driver';
        $hash = md5(sha1(var_export($config, TRUE)));
        
        if (!isset(self::$conns[$hash]))
        {
            self::$conns[$hash] = new $class($config);
        }
        
        if ($config['dbdriver'] == 'pdo' && strpos($config['hostname'], 'mysql') !== FALSE)
        {
            self::$conns[$hash]->simple_query('set names ' . $config['char_set']);
        }
        
        return self::$conns[$hash];
    }
}

class XP_DB extends XP_DB_active_record
{
    
}

class XP_DB_driver
{
    public $username;
    public $password;
    public $hostname;
    public $database;
    public $dbdriver = 'mysql';
    public $dbprefix = '';
    public $char_set = 'utf8';
    public $dbcollat = 'utf8_general_ci';
    public $autoinit = TRUE;
    public $swap_pre = '';
    public $port = '';
    public $pconnect = FALSE;
    public $conn_id = FALSE;
    public $result_id = FALSE;
    public $db_debug = FALSE;
    public $benchmark = 0;
    public $query_count = 0;
    public $bind_marker = '?';
    public $save_queries = TRUE;
    public $queries = array();
    public $query_times = array();
    public $data_cache = array();
    public $trans_enabled = TRUE;
    public $trans_strict = TRUE;
    public $_trans_depth = 0;
    public $_trans_status = TRUE;
    public $cache_on = FALSE;
    public $cachedir = '';
    public $cache_autodel = FALSE;
    public $CACHE;
    public $_protect_identifiers = TRUE;
    public $_reserved_identifiers = array('*');
    public $stmt_id;
    public $curs_id;
    public $limit_used;

    public function __construct($params)
    {
        if (is_array($params))
        {
            foreach ($params as $key => $val)
            {
                $this->{$key} = $val;
            }
        }
        log_message('debug', 'Database Driver Class Initialized');
    }

    public function initialize()
    {
        if (is_resource($this->conn_id) or is_object($this->conn_id)) {
            return TRUE;
        }

        $this->conn_id = $this->pconnect == FALSE ? $this->db_connect() : $this->db_pconnect();
        
        if (!$this->conn_id)
        {
            log_message('error', 'Unable to connect to the database');
            if ($this->db_debug)
            {
                $this->display_error('db_unable_to_connect');
            }
            return FALSE;
        }

        if ($this->database != '')
        {
            if (!$this->db_select())
            {

                log_message('error', 'Unable to select database: ' . $this->database);
                if ($this->db_debug) {
                    $this->display_error('db_unable_to_select', $this->database);
                }
                return FALSE;
            }
            else
            {
                if (!$this->db_set_charset($this->char_set, $this->dbcollat))
                {
                    return FALSE;
                }
                return TRUE;
            }
        }
        
        return TRUE;
    }

    public function db_set_charset($charset, $collation)
    {
        if (!$this->_db_set_charset($this->char_set, $this->dbcollat)) {
            log_message('error', 'Unable to set database connection charset: ' . $this->char_set);
            if ($this->db_debug) {
                $this->display_error('db_unable_to_set_charset', $this->char_set);
            }
            return FALSE;
        }
        return TRUE;
    }

    public function platform()
    {
        return $this->dbdriver;
    }

    public function version()
    {
        if (FALSE === ($sql = $this->_version())) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $driver_version_exceptions = array('oci8', 'sqlite', 'cubrid');
        if (in_array($this->dbdriver, $driver_version_exceptions)) {
            return $sql;
        } else {
            $query = $this->query($sql);
            return $query->row('ver');
        }
    }

    public function query($sql, $binds = FALSE, $return_object = TRUE)
    {
        if ($sql == '')
        {
            if ($this->db_debug)
            {
                log_message('error', 'Invalid query: ' . $sql);
                return $this->display_error('db_invalid_query');
            }
            return FALSE;
        }
        
        if (($this->dbprefix != '' and $this->swap_pre != '') and $this->dbprefix != $this->swap_pre)
        {
            $sql = preg_replace(('/(\\W)' . $this->swap_pre) . '(\\S+?)/', ('\\1' . $this->dbprefix) . '\\2', $sql);
        }
        
        if ($binds !== FALSE)
        {
            $sql = $this->compile_binds($sql, $binds);
        }
        
        if ($this->cache_on == TRUE and stristr($sql, 'SELECT'))
        {
            if ($this->_cache_init())
            {
                $this->load_rdriver();
                if (FALSE !== ($cache = $this->CACHE->read($sql)))
                {
                    return $cache;
                }
            }
        }
        
        if ($this->save_queries == TRUE)
        {
            $this->queries[] = $sql;
        }
        $time_start = (list($sm, $ss) = explode(' ', microtime()));        
        if (FALSE === ($this->result_id = $this->simple_query($sql)))
        {
            if ($this->save_queries == TRUE)
            {
                $this->query_times[] = 0;
            }
            
            $this->_trans_status = FALSE;
            if ($this->db_debug)
            {
                $error_no = $this->_error_number();
                $error_msg = $this->_error_message();
                $this->trans_complete();
                log_message('error', 'Query error: ' . $error_msg);
                return $this->display_error(array('Error Number: ' . $error_no, $error_msg, $sql));
            }
            return FALSE;
        }
        $time_end = (list($em, $es) = explode(' ', microtime()));
        $this->benchmark += ($em + $es) - ($sm + $ss);
        
        if ($this->save_queries == TRUE)
        {
            $this->query_times[] = ($em + $es) - ($sm + $ss);
        }
        $this->query_count++;
        
        if ($this->is_write_type($sql) === TRUE)
        {
            if (($this->cache_on == TRUE and $this->cache_autodel == TRUE) and $this->_cache_init())
            {
                $this->CACHE->delete();
            }
            return TRUE;
        }
        
        if ($return_object !== TRUE) {
            return TRUE;
        }
        
        $driver = $this->load_rdriver();        
        $RES = new $driver();
        $RES->conn_id = $this->conn_id;
        $RES->result_id = $this->result_id;
        
        if ($this->dbdriver == 'oci8')
        {
            $RES->stmt_id = $this->stmt_id;
            $RES->curs_id = NULL;
            $RES->limit_used = $this->limit_used;
            $this->stmt_id = FALSE;
        }
        
        $RES->num_rows = $RES->num_rows();
        if ($this->cache_on == TRUE and $this->_cache_init())
        {
            $CR = new XP_DB_result();
            $CR->num_rows = $RES->num_rows();
            $CR->result_object = $RES->result_object();
            $CR->result_array = $RES->result_array();
            $CR->conn_id = NULL;
            $CR->result_id = NULL;
            $this->CACHE->write($sql, $CR);
        }
        return $RES;
    }

    public function load_rdriver()
    {
        $driver = ('XP_DB_' . $this->dbdriver) . '_result';
        if (!class_exists($driver)) {
            include_once BASEPATH . 'database/DB_result.php';
            include_once ((((BASEPATH . 'database/drivers/') . $this->dbdriver) . '/') . $this->dbdriver) . '_result.php';
        }
        return $driver;
    }

    public function simple_query($sql)
    {
        if (!$this->conn_id) {
            $this->initialize();
        }
        return $this->_execute($sql);
    }

    public function trans_off()
    {
        $this->trans_enabled = FALSE;
    }

    public function trans_strict($mode = TRUE)
    {
        $this->trans_strict = is_bool($mode) ? $mode : TRUE;
    }

    public function trans_start($test_mode = FALSE)
    {
        if (!$this->trans_enabled) {
            return FALSE;
        }
        if ($this->_trans_depth > 0) {
            $this->_trans_depth += 1;
            return;
        }
        $this->trans_begin($test_mode);
    }

    public function trans_complete()
    {
        if (!$this->trans_enabled) {
            return FALSE;
        }
        if ($this->_trans_depth > 1) {
            $this->_trans_depth -= 1;
            return TRUE;
        }
        if ($this->_trans_status === FALSE) {
            $this->trans_rollback();
            if ($this->trans_strict === FALSE) {
                $this->_trans_status = TRUE;
            }
            log_message('debug', 'DB Transaction Failure');
            return FALSE;
        }
        $this->trans_commit();
        return TRUE;
    }

    public function trans_status()
    {
        return $this->_trans_status;
    }

    public function compile_binds($sql, $binds)
    {
        if (strpos($sql, $this->bind_marker) === FALSE) {
            return $sql;
        }
        if (!is_array($binds)) {
            $binds = array($binds);
        }
        $segments = explode($this->bind_marker, $sql);
        if (count($binds) >= count($segments)) {
            $binds = array_slice($binds, 0, count($segments) - 1);
        }
        $result = $segments[0];
        $i = 0;
        foreach ($binds as $bind) {
            $result .= $this->escape($bind);
            $result .= $segments[++$i];
        }
        return $result;
    }

    public function is_write_type($sql)
    {
        if (!preg_match('/^\\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD DATA|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\\s+/i', $sql)) {
            return FALSE;
        }
        return TRUE;
    }

    public function elapsed_time($decimals = 6)
    {
        return number_format($this->benchmark, $decimals);
    }

    public function total_queries()
    {
        return $this->query_count;
    }

    public function last_query()
    {
        return end($this->queries);
    }

    public function escape($str)
    {
        if (is_string($str)) {
            $str = ('\'' . $this->escape_str($str)) . '\'';
        } elseif (is_bool($str)) {
            $str = $str === FALSE ? 0 : 1;
        } elseif (is_null($str)) {
            $str = 'NULL';
        }
        return $str;
    }

    public function escape_like_str($str)
    {
        return $this->escape_str($str, TRUE);
    }

    public function primary($table = '')
    {
        $fields = $this->list_fields($table);
        if (!is_array($fields)) {
            return FALSE;
        }
        return current($fields);
    }

    public function list_tables($constrain_by_prefix = FALSE)
    {
        if (isset($this->data_cache['table_names'])) {
            return $this->data_cache['table_names'];
        }
        if (FALSE === ($sql = $this->_list_tables($constrain_by_prefix))) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $retval = array();
        $query = $this->query($sql);
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                if (isset($row['TABLE_NAME'])) {
                    $retval[] = $row['TABLE_NAME'];
                } else {
                    $retval[] = array_shift($row);
                }
            }
        }
        $this->data_cache['table_names'] = $retval;
        return $this->data_cache['table_names'];
    }

    public function table_exists($table_name)
    {
        return !in_array($this->_protect_identifiers($table_name, TRUE, FALSE, FALSE), $this->list_tables()) ? FALSE : TRUE;
    }

    public function list_fields($table = '')
    {
        if (isset($this->data_cache['field_names'][$table])) {
            return $this->data_cache['field_names'][$table];
        }
        if ($table == '') {
            if ($this->db_debug) {
                return $this->display_error('db_field_param_missing');
            }
            return FALSE;
        }
        if (FALSE === ($sql = $this->_list_columns($table))) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        }
        $query = $this->query($sql);
        $retval = array();
        foreach ($query->result_array() as $row) {
            if (isset($row['COLUMN_NAME'])) {
                $retval[] = $row['COLUMN_NAME'];
            } else {
                if ($this->dbdriver == 'sqlite3') {
                    $retval[] = $row['name'];
                } else {
                    $retval[] = current($row);
                }
            }
        }
        $this->data_cache['field_names'][$table] = $retval;
        return $this->data_cache['field_names'][$table];
    }

    public function field_exists($field_name, $table_name)
    {
        return !in_array($field_name, $this->list_fields($table_name)) ? FALSE : TRUE;
    }

    public function field_data($table = '')
    {
        if ($table == '') {
            if ($this->db_debug) {
                return $this->display_error('db_field_param_missing');
            }
            return FALSE;
        }
        $query = $this->query($this->_field_data($this->_protect_identifiers($table, TRUE, NULL, FALSE)));
        return $query->field_data();
    }

    public function insert_string($table, $data)
    {
        $fields = array();
        $values = array();
        foreach ($data as $key => $val) {
            $fields[] = $this->_escape_identifiers($key);
            $values[] = $this->escape($val);
        }
        return $this->_insert($this->_protect_identifiers($table, TRUE, NULL, FALSE), $fields, $values);
    }

    public function update_string($table, $data, $where)
    {
        if ($where == '') {
            return false;
        }
        $fields = array();
        foreach ($data as $key => $val) {
            $fields[$this->_protect_identifiers($key)] = $this->escape($val);
        }
        if (!is_array($where)) {
            $dest = array($where);
        } else {
            $dest = array();
            foreach ($where as $key => $val) {
                $prefix = count($dest) == 0 ? '' : ' AND ';
                if ($val !== '') {
                    if (!$this->_has_operator($key)) {
                        $key .= ' =';
                    }
                    $val = ' ' . $this->escape($val);
                }
                $dest[] = ($prefix . $key) . $val;
            }
        }
        return $this->_update($this->_protect_identifiers($table, TRUE, NULL, FALSE), $fields, $dest);
    }

    public function _has_operator($str)
    {
        $str = trim($str);
        if (!preg_match('/(\\s|<|>|!|=|is null|is not null)/i', $str)) {
            return FALSE;
        }
        return TRUE;
    }

    public function call_function($function)
    {
        $driver = $this->dbdriver == 'postgre' ? 'pg_' : $this->dbdriver . '_';
        if (FALSE === strpos($driver, $function)) {
            $function = $driver . $function;
        }
        if (!function_exists($function)) {
            if ($this->db_debug) {
                return $this->display_error('db_unsupported_function');
            }
            return FALSE;
        } else {
            $args = func_num_args() > 1 ? array_splice(func_get_args(), 1) : null;
            if (is_null($args)) {
                return call_user_func($function);
            } else {
                return call_user_func_array($function, $args);
            }
        }
    }

    public function cache_set_path($path = '')
    {
        $this->cachedir = $path;
    }

    public function cache_on()
    {
        $this->cache_on = TRUE;
        return TRUE;
    }

    public function cache_off()
    {
        $this->cache_on = FALSE;
        return FALSE;
    }

    public function cache_delete($segment_one = '', $segment_two = '')
    {
        if (!$this->_cache_init()) {
            return FALSE;
        }
        return $this->CACHE->delete($segment_one, $segment_two);
    }

    public function cache_delete_all()
    {
        if (!$this->_cache_init()) {
            return FALSE;
        }
        return $this->CACHE->delete_all();
    }

    public function _cache_init()
    {
        if (is_object($this->CACHE) and class_exists('XP_DB_Cache')) {
            return TRUE;
        }
        if (!class_exists('XP_DB_Cache')) {
            if (!@include (BASEPATH . 'database/DB_cache.php')) {
                return $this->cache_off();
            }
        }
        $this->CACHE = new XP_DB_Cache($this);
        return TRUE;
    }

    public function close()
    {
        if (is_resource($this->conn_id) or is_object($this->conn_id)) {
            $this->_close($this->conn_id);
        }
        $this->conn_id = FALSE;
    }

    public function display_error($error = '', $swap = '', $native = FALSE)
    {
        $msg = '';
        if (is_array($error)) {
            foreach ($error as $m) {
                $msg .= $m . '</br>';
            }
        } else {
            $msg = $error;
        }
        global $db, $system;
        if ($db[$db['active_group']]['db_debug']) {
            header('HTTP/1.1 500 Internal Server Database Error');
            if ( !empty($system['error_page_db']) && file_exists( APP_FOLDER . DIRECTORY_SEPARATOR . $system['error_page_db'] ) ) {
                include APP_FOLDER . DIRECTORY_SEPARATOR . $system['error_page_db'];
            } else {
                echo $msg;
            }
        }
        die;
    }

    public function protect_identifiers($item, $prefix_single = FALSE)
    {
        return $this->_protect_identifiers($item, $prefix_single);
    }

    public function _protect_identifiers($item, $prefix_single = FALSE, $protect_identifiers = NULL, $field_exists = TRUE)
    {
        if (!is_bool($protect_identifiers)) {
            $protect_identifiers = $this->_protect_identifiers;
        }
        if (is_array($item)) {
            $escaped_array = array();
            foreach ($item as $k => $v) {
                $escaped_array[$this->_protect_identifiers($k)] = $this->_protect_identifiers($v);
            }
            return $escaped_array;
        }
        $item = preg_replace('/[\\t ]+/', ' ', $item);
        if (strpos($item, ' ') !== FALSE) {
            $alias = strstr($item, ' ');
            $item = substr($item, 0, -strlen($alias));
        } else {
            $alias = '';
        }
        if (strpos($item, '(') !== FALSE) {
            return $item . $alias;
        }
        if (strpos($item, '.') !== FALSE) {
            $parts = explode('.', $item);
            if (in_array($parts[0], $this->ar_aliased_tables)) {
                if ($protect_identifiers === TRUE) {
                    foreach ($parts as $key => $val) {
                        if (!in_array($val, $this->_reserved_identifiers)) {
                            $parts[$key] = $this->_escape_identifiers($val);
                        }
                    }
                    $item = implode('.', $parts);
                }
                return $item . $alias;
            }
            if ($this->dbprefix != '') {
                if (isset($parts[3])) {
                    $i = 2;
                } elseif (isset($parts[2])) {
                    $i = 1;
                } else {
                    $i = 0;
                }
                if ($field_exists == FALSE) {
                    $i++;
                }
                if ($this->swap_pre != '' && strncmp($parts[$i], $this->swap_pre, strlen($this->swap_pre)) === 0) {
                    $parts[$i] = preg_replace(('/^' . $this->swap_pre) . '(\\S+?)/', $this->dbprefix . '\\1', $parts[$i]);
                }
                if (substr($parts[$i], 0, strlen($this->dbprefix)) != $this->dbprefix) {
                    $parts[$i] = $this->dbprefix . $parts[$i];
                }
                $item = implode('.', $parts);
            }
            if ($protect_identifiers === TRUE) {
                $item = $this->_escape_identifiers($item);
            }
            return $item . $alias;
        }
        if ($this->dbprefix != '') {
            if ($this->swap_pre != '' && strncmp($item, $this->swap_pre, strlen($this->swap_pre)) === 0) {
                $item = preg_replace(('/^' . $this->swap_pre) . '(\\S+?)/', $this->dbprefix . '\\1', $item);
            }
            if ($prefix_single == TRUE and substr($item, 0, strlen($this->dbprefix)) != $this->dbprefix) {
                $item = $this->dbprefix . $item;
            }
        }
        if ($protect_identifiers === TRUE and !in_array($item, $this->_reserved_identifiers)) {
            $item = $this->_escape_identifiers($item);
        }
        return $item . $alias;
    }

    protected function _reset_select()
    {
        
    }
}

class XP_DB_result
{
    public $conn_id = NULL;
    public $result_id = NULL;
    public $result_array = array();
    public $result_object = array();
    public $custom_result_object = array();
    public $current_row = 0;
    public $num_rows = 0;
    public $row_data = NULL;

    public function result($type = 'object')
    {
        if ($type == 'array') {
            return $this->result_array();
        } else {
            if ($type == 'object') {
                return $this->result_object();
            } else {
                return $this->custom_result_object($type);
            }
        }
    }

    public function custom_result_object($class_name)
    {
        if (array_key_exists($class_name, $this->custom_result_object)) {
            return $this->custom_result_object[$class_name];
        }
        if ($this->result_id === FALSE or $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        $result_object = array();
        while ($row = $this->_fetch_object()) {
            $object = new $class_name();
            foreach ($row as $key => $value) {
                $object->{$key} = $value;
            }
            $result_object[] = $object;
        }
        return $this->custom_result_object[$class_name] = $result_object;
    }

    public function result_object()
    {
        if (count($this->result_object) > 0) {
            return $this->result_object;
        }
        if ($this->result_id === FALSE or $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        while ($row = $this->_fetch_object()) {
            $this->result_object[] = $row;
        }
        return $this->result_object;
    }

    public function result_array()
    {
        if (count($this->result_array) > 0) {
            return $this->result_array;
        }
        if ($this->result_id === FALSE or $this->num_rows() == 0) {
            return array();
        }
        $this->_data_seek(0);
        while ($row = $this->_fetch_assoc()) {
            $this->result_array[] = $row;
        }
        return $this->result_array;
    }

    public function row($n = 0, $type = 'object')
    {
        if (!is_numeric($n)) {
            if (!is_array($this->row_data)) {
                $this->row_data = $this->row_array(0);
            }
            if (array_key_exists($n, $this->row_data)) {
                return $this->row_data[$n];
            }
            $n = 0;
        }
        if ($type == 'object') {
            return $this->row_object($n);
        } else {
            if ($type == 'array') {
                return $this->row_array($n);
            } else {
                return $this->custom_row_object($n, $type);
            }
        }
    }

    public function set_row($key, $value = NULL)
    {
        if (!is_array($this->row_data)) {
            $this->row_data = $this->row_array(0);
        }
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->row_data[$k] = $v;
            }
            return;
        }
        if ($key != '' and !is_null($value)) {
            $this->row_data[$key] = $value;
        }
    }

    public function custom_row_object($n, $type)
    {
        $result = $this->custom_result_object($type);
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row and isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }

    public function row_object($n = 0)
    {
        $result = $this->result_object();
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row and isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }

    public function row_array($n = 0)
    {
        $result = $this->result_array();
        if (count($result) == 0) {
            return $result;
        }
        if ($n != $this->current_row and isset($result[$n])) {
            $this->current_row = $n;
        }
        return $result[$this->current_row];
    }

    public function first_row($type = 'object')
    {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        return $result[0];
    }

    public function last_row($type = 'object')
    {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        return $result[count($result) - 1];
    }

    public function next_row($type = 'object')
    {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        if (isset($result[$this->current_row + 1])) {
            ++$this->current_row;
        }
        return $result[$this->current_row];
    }

    public function previous_row($type = 'object')
    {
        $result = $this->result($type);
        if (count($result) == 0) {
            return $result;
        }
        if (isset($result[$this->current_row - 1])) {
            --$this->current_row;
        }
        return $result[$this->current_row];
    }

    public function num_rows()
    {
        return $this->num_rows;
    }

    public function num_fields()
    {
        return 0;
    }

    public function list_fields()
    {
        return array();
    }

    public function field_data()
    {
        return array();
    }

    public function free_result()
    {
        return TRUE;
    }

    protected function _data_seek()
    {
        return TRUE;
    }

    protected function _fetch_assoc()
    {
        return array();
    }

    protected function _fetch_object()
    {
        return array();
    }
}

class XP_DB_active_record extends XP_DB_driver
{
    public $ar_select = array();
    public $ar_distinct = FALSE;
    public $ar_from = array();
    public $ar_join = array();
    public $ar_where = array();
    public $ar_like = array();
    public $ar_groupby = array();
    public $ar_having = array();
    public $ar_keys = array();
    public $ar_limit = FALSE;
    public $ar_offset = FALSE;
    public $ar_order = FALSE;
    public $ar_orderby = array();
    public $ar_set = array();
    public $ar_wherein = array();
    public $ar_aliased_tables = array();
    public $ar_store_array = array();
    public $ar_caching = FALSE;
    public $ar_cache_exists = array();
    public $ar_cache_select = array();
    public $ar_cache_from = array();
    public $ar_cache_join = array();
    public $ar_cache_where = array();
    public $ar_cache_like = array();
    public $ar_cache_groupby = array();
    public $ar_cache_having = array();
    public $ar_cache_orderby = array();
    public $ar_cache_set = array();
    public $ar_no_escape = array();
    public $ar_cache_no_escape = array();

    public function select($select = '*', $escape = NULL)
    {
        if (is_string($select)) {
            $select = explode(',', $select);
        }
        foreach ($select as $val) {
            $val = trim($val);
            if ($val != '') {
                $this->ar_select[] = $val;
                $this->ar_no_escape[] = $escape;
                if ($this->ar_caching === TRUE) {
                    $this->ar_cache_select[] = $val;
                    $this->ar_cache_exists[] = 'select';
                    $this->ar_cache_no_escape[] = $escape;
                }
            }
        }
        return $this;
    }

    public function select_max($select = '', $alias = '')
    {
        return $this->_max_min_avg_sum($select, $alias, 'MAX');
    }

    public function select_min($select = '', $alias = '')
    {
        return $this->_max_min_avg_sum($select, $alias, 'MIN');
    }

    public function select_avg($select = '', $alias = '')
    {
        return $this->_max_min_avg_sum($select, $alias, 'AVG');
    }

    public function select_sum($select = '', $alias = '')
    {
        return $this->_max_min_avg_sum($select, $alias, 'SUM');
    }

    protected function _max_min_avg_sum($select = '', $alias = '', $type = 'MAX')
    {
        if (!is_string($select) or $select == '') {
            $this->display_error('db_invalid_query');
        }
        $type = strtoupper($type);
        if (!in_array($type, array('MAX', 'MIN', 'AVG', 'SUM'))) {
            show_error('Invalid function type: ' . $type);
        }
        if ($alias == '') {
            $alias = $this->_create_alias_from_table(trim($select));
        }
        $sql = ((($type . '(') . $this->_protect_identifiers(trim($select))) . ') AS ') . $alias;
        $this->ar_select[] = $sql;
        if ($this->ar_caching === TRUE) {
            $this->ar_cache_select[] = $sql;
            $this->ar_cache_exists[] = 'select';
        }
        return $this;
    }

    protected function _create_alias_from_table($item)
    {
        if (strpos($item, '.') !== FALSE) {
            return end(explode('.', $item));
        }
        return $item;
    }

    public function distinct($val = TRUE)
    {
        $this->ar_distinct = is_bool($val) ? $val : TRUE;
        return $this;
    }

    public function from($from)
    {
        foreach ((array) $from as $val) {
            if (strpos($val, ',') !== FALSE) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    $this->_track_aliases($v);
                    $this->ar_from[] = $this->_protect_identifiers($v, TRUE, NULL, FALSE);
                    if ($this->ar_caching === TRUE) {
                        $this->ar_cache_from[] = $this->_protect_identifiers($v, TRUE, NULL, FALSE);
                        $this->ar_cache_exists[] = 'from';
                    }
                }
            } else {
                $val = trim($val);
                $this->_track_aliases($val);
                $this->ar_from[] = $this->_protect_identifiers($val, TRUE, NULL, FALSE);
                if ($this->ar_caching === TRUE) {
                    $this->ar_cache_from[] = $this->_protect_identifiers($val, TRUE, NULL, FALSE);
                    $this->ar_cache_exists[] = 'from';
                }
            }
        }
        return $this;
    }

    public function join($table, $cond, $type = '')
    {
        if ($type != '') {
            $type = strtoupper(trim($type));
            if (!in_array($type, array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'))) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }
        $this->_track_aliases($table);
        if (preg_match('/([\\w\\.]+)([\\W\\s]+)(.+)/', $cond, $match)) {
            $match[1] = $this->_protect_identifiers($match[1]);
            $match[3] = $this->_protect_identifiers($match[3]);
            $cond = ($match[1] . $match[2]) . $match[3];
        }
        $join = ((($type . 'JOIN ') . $this->_protect_identifiers($table, TRUE, NULL, FALSE)) . ' ON ') . $cond;
        $this->ar_join[] = $join;
        if ($this->ar_caching === TRUE) {
            $this->ar_cache_join[] = $join;
            $this->ar_cache_exists[] = 'join';
        }
        return $this;
    }

    public function where($key, $value = NULL, $escape = TRUE)
    {
        return $this->_where($key, $value, 'AND ', $escape);
    }

    public function or_where($key, $value = NULL, $escape = TRUE)
    {
        return $this->_where($key, $value, 'OR ', $escape);
    }

    protected function _where($key, $value = NULL, $type = 'AND ', $escape = NULL)
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        if (!is_bool($escape)) {
            $escape = $this->_protect_identifiers;
        }
        foreach ($key as $k => $v) {
            $prefix = (count($this->ar_where) == 0 and count($this->ar_cache_where) == 0) ? '' : $type;
            if (is_null($v) && !$this->_has_operator($k)) {
                $k .= ' IS NULL';
            }
            if (!is_null($v)) {
                if ($escape === TRUE) {
                    $k = $this->_protect_identifiers($k, FALSE, $escape);
                    $v = ' ' . $this->escape($v);
                }
                if (!$this->_has_operator($k)) {
                    $k .= ' = ';
                }
            } else {
                $k = $this->_protect_identifiers($k, FALSE, $escape);
            }
            $this->ar_where[] = ($prefix . $k) . $v;
            if ($this->ar_caching === TRUE) {
                $this->ar_cache_where[] = ($prefix . $k) . $v;
                $this->ar_cache_exists[] = 'where';
            }
        }
        return $this;
    }

    public function where_in($key = NULL, $values = NULL)
    {
        return $this->_where_in($key, $values);
    }

    public function or_where_in($key = NULL, $values = NULL)
    {
        return $this->_where_in($key, $values, FALSE, 'OR ');
    }

    public function where_not_in($key = NULL, $values = NULL)
    {
        return $this->_where_in($key, $values, TRUE);
    }

    public function or_where_not_in($key = NULL, $values = NULL)
    {
        return $this->_where_in($key, $values, TRUE, 'OR ');
    }

    protected function _where_in($key = NULL, $values = NULL, $not = FALSE, $type = 'AND ')
    {
        if ($key === NULL or $values === NULL) {
            return;
        }
        if (!is_array($values)) {
            $values = array($values);
        }
        $not = $not ? ' NOT' : '';
        foreach ($values as $value) {
            $this->ar_wherein[] = $this->escape($value);
        }
        $prefix = count($this->ar_where) == 0 ? '' : $type;
        $where_in = (((($prefix . $this->_protect_identifiers($key)) . $not) . ' IN (') . implode(', ', $this->ar_wherein)) . ') ';
        $this->ar_where[] = $where_in;
        if ($this->ar_caching === TRUE) {
            $this->ar_cache_where[] = $where_in;
            $this->ar_cache_exists[] = 'where';
        }
        $this->ar_wherein = array();
        return $this;
    }

    public function like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'AND ', $side);
    }

    public function not_like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'AND ', $side, 'NOT');
    }

    public function or_like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'OR ', $side);
    }

    public function or_not_like($field, $match = '', $side = 'both')
    {
        return $this->_like($field, $match, 'OR ', $side, 'NOT');
    }

    protected function _like($field, $match = '', $type = 'AND ', $side = 'both', $not = '')
    {
        if (!is_array($field)) {
            $field = array($field => $match);
        }
        foreach ($field as $k => $v) {
            $k = $this->_protect_identifiers($k);
            $prefix = count($this->ar_like) == 0 ? '' : $type;
            $v = $this->escape_like_str($v);
            if ($side == 'none') {
                $like_statement = $prefix . " {$k} {$not} LIKE '{$v}'";
            } elseif ($side == 'before') {
                $like_statement = $prefix . " {$k} {$not} LIKE '%{$v}'";
            } elseif ($side == 'after') {
                $like_statement = $prefix . " {$k} {$not} LIKE '{$v}%'";
            } else {
                $like_statement = $prefix . " {$k} {$not} LIKE '%{$v}%'";
            }
            if ($this->_like_escape_str != '') {
                $like_statement = $like_statement . sprintf($this->_like_escape_str, $this->_like_escape_chr);
            }
            $this->ar_like[] = $like_statement;
            if ($this->ar_caching === TRUE) {
                $this->ar_cache_like[] = $like_statement;
                $this->ar_cache_exists[] = 'like';
            }
        }
        return $this;
    }

    public function group_by($by)
    {
        if (is_string($by)) {
            $by = explode(',', $by);
        }
        foreach ($by as $val) {
            $val = trim($val);
            if ($val != '') {
                $this->ar_groupby[] = $this->_protect_identifiers($val);
                if ($this->ar_caching === TRUE) {
                    $this->ar_cache_groupby[] = $this->_protect_identifiers($val);
                    $this->ar_cache_exists[] = 'groupby';
                }
            }
        }
        return $this;
    }

    public function having($key, $value = '', $escape = TRUE)
    {
        return $this->_having($key, $value, 'AND ', $escape);
    }

    public function or_having($key, $value = '', $escape = TRUE)
    {
        return $this->_having($key, $value, 'OR ', $escape);
    }

    protected function _having($key, $value = '', $type = 'AND ', $escape = TRUE)
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        foreach ($key as $k => $v) {
            $prefix = count($this->ar_having) == 0 ? '' : $type;
            if ($escape === TRUE) {
                $k = $this->_protect_identifiers($k);
            }
            if (!$this->_has_operator($k)) {
                $k .= ' = ';
            }
            if ($v != '') {
                $v = ' ' . $this->escape($v);
            }
            $this->ar_having[] = ($prefix . $k) . $v;
            if ($this->ar_caching === TRUE) {
                $this->ar_cache_having[] = ($prefix . $k) . $v;
                $this->ar_cache_exists[] = 'having';
            }
        }
        return $this;
    }

    public function order_by($orderby, $direction = '')
    {
        if (strtolower($direction) == 'random') {
            $orderby = '';
            $direction = $this->_random_keyword;
        } elseif (trim($direction) != '') {
            $direction = in_array(strtoupper(trim($direction)), array('ASC', 'DESC'), TRUE) ? ' ' . $direction : ' ASC';
        }
        if (strpos($orderby, ',') !== FALSE) {
            $temp = array();
            foreach (explode(',', $orderby) as $part) {
                $part = trim($part);
                if (!in_array($part, $this->ar_aliased_tables)) {
                    $part = $this->_protect_identifiers(trim($part));
                }
                $temp[] = $part;
            }
            $orderby = implode(', ', $temp);
        } else {
            if ($direction != $this->_random_keyword) {
                $orderby = $this->_protect_identifiers($orderby);
            }
        }
        $orderby_statement = $orderby . $direction;
        $this->ar_orderby[] = $orderby_statement;
        if ($this->ar_caching === TRUE) {
            $this->ar_cache_orderby[] = $orderby_statement;
            $this->ar_cache_exists[] = 'orderby';
        }
        return $this;
    }

    public function limit($value, $offset = '')
    {
        $this->ar_limit = (int) $value;
        if ($offset != '') {
            $this->ar_offset = (int) $offset;
        }
        return $this;
    }

    public function offset($offset)
    {
        $this->ar_offset = $offset;
        return $this;
    }

    public function set($key, $value = '', $escape = TRUE)
    {
        $key = $this->_object_to_array($key);
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        foreach ($key as $k => $v) {
            if ($escape === FALSE) {
                $this->ar_set[$this->_protect_identifiers($k)] = $v;
            } else {
                $this->ar_set[$this->_protect_identifiers($k, FALSE, TRUE)] = $this->escape($v);
            }
        }
        return $this;
    }

    public function get($table = '', $limit = null, $offset = null)
    {
        if ($table != '')
        {
            $this->_track_aliases($table);
            $this->from($table);
        }

        if (!is_null($limit))
        {
            $this->limit($limit, $offset);
        }

        $sql = $this->_compile_select();
        $result = $this->query($sql);
        $this->_reset_select();

        return $result;
    }

    public function count_all_results($table = '')
    {
        if ($table != '') {
            $this->_track_aliases($table);
            $this->from($table);
        }
        $sql = $this->_compile_select($this->_count_string . $this->_protect_identifiers('numrows'));
        $query = $this->query($sql);
        $this->_reset_select();
        if ($query->num_rows() == 0) {
            return 0;
        }
        $row = $query->row();
        return (int) $row->numrows;
    }

    public function get_where($table = '', $where = null, $limit = null, $offset = null)
    {
        if ($table != '')
        {
            $this->from($table);
        }
        
        if (!is_null($where))
        {
            $this->where($where);
        }
        
        if (!is_null($limit))
        {
            $this->limit($limit, $offset);
        }
        
        $sql = $this->_compile_select();
        $result = $this->query($sql);
        $this->_reset_select();
        
        return $result;
    }

    public function insert_batch($table = '', $set = NULL)
    {
        if (!is_null($set)) {
            $this->set_insert_batch($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {
            $sql = $this->_insert_batch($this->_protect_identifiers($table, TRUE, NULL, FALSE), $this->ar_keys, array_slice($this->ar_set, $i, 100));
            $this->query($sql);
        }
        $this->_reset_write();
        return TRUE;
    }

    public function set_insert_batch($key, $value = '', $escape = TRUE)
    {
        $key = $this->_object_to_array_batch($key);
        if (!is_array($key)) {
            $key = array($key => $value);
        }
        $keys = array_keys(current($key));
        sort($keys);
        foreach ($key as $row) {
            if (count(array_diff($keys, array_keys($row))) > 0 or count(array_diff(array_keys($row), $keys)) > 0) {
                $this->ar_set[] = array();
                return;
            }
            ksort($row);
            if ($escape === FALSE) {
                $this->ar_set[] = ('(' . implode(',', $row)) . ')';
            } else {
                $clean = array();
                foreach ($row as $value) {
                    $clean[] = $this->escape($value);
                }
                $this->ar_set[] = ('(' . implode(',', $clean)) . ')';
            }
        }
        foreach ($keys as $k) {
            $this->ar_keys[] = $this->_protect_identifiers($k);
        }
        return $this;
    }

    public function insert($table = '', $set = NULL)
    {
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        $sql = $this->_insert($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_keys($this->ar_set), array_values($this->ar_set));
        $this->_reset_write();
        return $this->query($sql);
    }

    public function replace($table = '', $set = NULL)
    {
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        $sql = $this->_replace($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_keys($this->ar_set), array_values($this->ar_set));
        $this->_reset_write();
        return $this->query($sql);
    }

    public function update($table = '', $set = NULL, $where = NULL, $limit = NULL)
    {
        $this->_merge_cache();
        if (!is_null($set)) {
            $this->set($set);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        if ($where != NULL) {
            $this->where($where);
        }
        if ($limit != NULL) {
            $this->limit($limit);
        }
        $sql = $this->_update($this->_protect_identifiers($table, TRUE, NULL, FALSE), $this->ar_set, $this->ar_where, $this->ar_orderby, $this->ar_limit);
        $this->_reset_write();
        return $this->query($sql);
    }

    public function update_batch($table = '', $set = NULL, $index = NULL)
    {
        $this->_merge_cache();
        if (is_null($index)) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_index');
            }
            return FALSE;
        }
        if (!is_null($set)) {
            $this->set_update_batch($set, $index);
        }
        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_must_use_set');
            }
            return FALSE;
        }
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        }
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {
            $sql = $this->_update_batch($this->_protect_identifiers($table, TRUE, NULL, FALSE), array_slice($this->ar_set, $i, 100), $this->_protect_identifiers($index), $this->ar_where);
            $this->query($sql);
        }
        $this->_reset_write();
    }

    public function set_update_batch($key, $index = '', $escape = TRUE)
    {
        $key = $this->_object_to_array_batch($key);
        if (!is_array($key)) {
            
        }
        foreach ($key as $k => $v) {
            $index_set = FALSE;
            $clean = array();
            foreach ($v as $k2 => $v2) {
                if ($k2 == $index) {
                    $index_set = TRUE;
                } else {
                    $not[] = ($k . '-') . $v;
                }
                if ($escape === FALSE) {
                    $clean[$this->_protect_identifiers($k2)] = $v2;
                } else {
                    $clean[$this->_protect_identifiers($k2)] = $this->escape($v2);
                }
            }
            if ($index_set == FALSE) {
                return $this->display_error('db_batch_missing_index');
            }
            $this->ar_set[] = $clean;
        }
        return $this;
    }

    public function empty_table($table = '')
    {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        $sql = $this->_delete($table);
        $this->_reset_write();
        return $this->query($sql);
    }

    public function truncate($table = '')
    {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        $sql = $this->_truncate($table);
        $this->_reset_write();
        return $this->query($sql);
    }

    public function delete($table = '', $where = '', $limit = NULL, $reset_data = TRUE)
    {
        $this->_merge_cache();
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->display_error('db_must_set_table');
                }
                return FALSE;
            }
            $table = $this->ar_from[0];
        } elseif (is_array($table)) {
            foreach ($table as $single_table) {
                $this->delete($single_table, $where, $limit, FALSE);
            }
            $this->_reset_write();
            return;
        } else {
            $table = $this->_protect_identifiers($table, TRUE, NULL, FALSE);
        }
        if ($where != '') {
            $this->where($where);
        }
        if ($limit != NULL) {
            $this->limit($limit);
        }
        if ((count($this->ar_where) == 0 && count($this->ar_wherein) == 0) && count($this->ar_like) == 0) {
            if ($this->db_debug) {
                return $this->display_error('db_del_must_use_where');
            }
            return FALSE;
        }
        $sql = $this->_delete($table, $this->ar_where, $this->ar_like, $this->ar_limit);
        if ($reset_data) {
            $this->_reset_write();
        }
        return $this->query($sql);
    }

    public function dbprefix($table = '')
    {
        if ($table == '') {
            $this->display_error('db_table_name_required');
        }
        return $this->dbprefix . $table;
    }

    public function set_dbprefix($prefix = '')
    {
        return $this->dbprefix = $prefix;
    }

    protected function _track_aliases($table)
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->_track_aliases($t);
            }
            return;
        }
        if (strpos($table, ',') !== FALSE) {
            return $this->_track_aliases(explode(',', $table));
        }
        if (strpos($table, ' ') !== FALSE) {
            $table = preg_replace('/\\s+AS\\s+/i', ' ', $table);
            $table = trim(strrchr($table, ' '));
            if (!in_array($table, $this->ar_aliased_tables)) {
                $this->ar_aliased_tables[] = $table;
            }
        }
    }

    protected function _compile_select($select_override = FALSE)
    {
        $this->_merge_cache();

        if ($select_override !== FALSE)
        {
            $sql = $select_override;
        }
        else
        {
            $sql = !$this->ar_distinct ? 'SELECT ' : 'SELECT DISTINCT ';
            if (count($this->ar_select) == 0)
            {
                $sql .= '*';
            }
            else
            {
                foreach ($this->ar_select as $key => $val)
                {
                    $no_escape = isset($this->ar_no_escape[$key]) ? $this->ar_no_escape[$key] : NULL;
                    $this->ar_select[$key] = $this->_protect_identifiers($val, FALSE, $no_escape);
                }
                $sql .= implode(', ', $this->ar_select);
            }
        }

        if (count($this->ar_from) > 0)
        {
            $sql .= "\nFROM ";
            $sql .= $this->_from_tables($this->ar_from);
        }

        if (count($this->ar_join) > 0)
        {
            $sql .= "\n";
            $sql .= implode("\n", $this->ar_join);
        }

        if (count($this->ar_where) > 0 or count($this->ar_like) > 0)
        {
            $sql .= "\nWHERE ";
        }
        $sql .= implode("\n", $this->ar_where);

        if (count($this->ar_like) > 0)
        {
            $end = '';
            if (count($this->ar_where) > 0)
            {
                $sql .= "\nAND (";
                $end = ' ) ';
            }
            $sql .= implode("\n", $this->ar_like) . $end;
        }

        if (count($this->ar_groupby) > 0)
        {
            $sql .= "\nGROUP BY ";
            $sql .= implode(', ', $this->ar_groupby);
        }

        if (count($this->ar_having) > 0)
        {
            $sql .= "\nHAVING ";
            $sql .= implode("\n", $this->ar_having);
        }

        if (count($this->ar_orderby) > 0)
        {
            $sql .= "\nORDER BY ";
            $sql .= implode(', ', $this->ar_orderby);
            if ($this->ar_order !== FALSE)
            {
                $sql .= $this->ar_order == 'desc' ? ' DESC' : ' ASC';
            }
        }

        if (is_numeric($this->ar_limit))
        {
            $sql .= "\n";
            $sql = $this->_limit($sql, $this->ar_limit, $this->ar_offset);
        }

        return $sql;
    }

    public function _object_to_array($object)
    {
        if (!is_object($object)) {
            return $object;
        }
        $array = array();
        foreach (get_object_vars($object) as $key => $val) {
            if ((!is_object($val) && !is_array($val)) && $key != '_parent_name') {
                $array[$key] = $val;
            }
        }
        return $array;
    }

    public function _object_to_array_batch($object)
    {
        if (!is_object($object)) {
            return $object;
        }
        $array = array();
        $out = get_object_vars($object);
        $fields = array_keys($out);
        foreach ($fields as $val) {
            if ($val != '_parent_name') {
                $i = 0;
                foreach ($out[$val] as $data) {
                    $array[$i][$val] = $data;
                    $i++;
                }
            }
        }
        return $array;
    }

    public function start_cache()
    {
        $this->ar_caching = TRUE;
    }

    public function stop_cache()
    {
        $this->ar_caching = FALSE;
    }

    public function flush_cache()
    {
        $this->_reset_run(array('ar_cache_select' => array(), 'ar_cache_from' => array(), 'ar_cache_join' => array(), 'ar_cache_where' => array(), 'ar_cache_like' => array(), 'ar_cache_groupby' => array(), 'ar_cache_having' => array(), 'ar_cache_orderby' => array(), 'ar_cache_set' => array(), 'ar_cache_exists' => array(), 'ar_cache_no_escape' => array()));
    }

    protected function _merge_cache()
    {
        if (count($this->ar_cache_exists) == 0) {
            return;
        }
        foreach ($this->ar_cache_exists as $val) {
            $ar_variable = 'ar_' . $val;
            $ar_cache_var = 'ar_cache_' . $val;
            if (count($this->{$ar_cache_var}) == 0) {
                continue;
            }
            $this->{$ar_variable} = array_unique(array_merge($this->{$ar_cache_var}, $this->{$ar_variable}));
        }
        if ($this->_protect_identifiers === TRUE and count($this->ar_cache_from) > 0) {
            $this->_track_aliases($this->ar_from);
        }
        $this->ar_no_escape = $this->ar_cache_no_escape;
    }

    protected function _reset_run($ar_reset_items)
    {
        foreach ($ar_reset_items as $item => $default_value) {
            if (!in_array($item, $this->ar_store_array)) {
                $this->{$item} = $default_value;
            }
        }
    }

    protected function _reset_select()
    {
        $ar_reset_items = array('ar_select' => array(), 'ar_from' => array(), 'ar_join' => array(), 'ar_where' => array(), 'ar_like' => array(), 'ar_groupby' => array(), 'ar_having' => array(), 'ar_orderby' => array(), 'ar_wherein' => array(), 'ar_aliased_tables' => array(), 'ar_no_escape' => array(), 'ar_distinct' => FALSE, 'ar_limit' => FALSE, 'ar_offset' => FALSE, 'ar_order' => FALSE);
        $this->_reset_run($ar_reset_items);
    }

    protected function _reset_write()
    {
        $ar_reset_items = array('ar_set' => array(), 'ar_from' => array(), 'ar_where' => array(), 'ar_like' => array(), 'ar_orderby' => array(), 'ar_keys' => array(), 'ar_limit' => FALSE, 'ar_order' => FALSE);
        $this->_reset_run($ar_reset_items);
    }
}

if ( !class_exists('XP_DB_mysql_driver') || !class_exists('XP_DB_mysql_result') ) include(__DIR__ . '/driver/db.mysql.php');
