<?php
/**
 * Neevo - Tiny open-source MySQL layer for PHP
 *
 * Copyright (c) 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @copyright  Copyright (c) 2010 Martin Srank (http://smasty.net)
 * @license    http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link       http://labs.smasty.net/neevo/
 * @package    Neevo
 * @version    0.01dev
 *
 */


/** Main Neevo layer class
 * @package Neevo
 */
class Neevo{

/*  VARIABLES  */

  var $resource_ID;
  var $queries = 0;
  var $last = '';
  var $error_reporting = 1;
  var $logging = false;
  var $log_file = '';
  var $table_prefix = '';
  private $options = array();

  /** @var array Array of HTML colors for SQL highlighter */
  static $highlight_colors = array(
    'background' => '#f9f9f9',
    'columns'    => '#0000ff',
    'chars'      => '#000000',
    'keywords'   => '#008000',
    'joins'      => '#555555',
    'functions'  => '#008000',
    'constants'  => '#ff0000'
    );


/*  CONSTANTS  */
  const ASSOC=1;
  const NUM=2;
  const OBJECT=3;


  /**
   * Constructor
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host         =>  mysql_host,
   *   username     =>  mysql_username,
   *   password     =>  mysql_password,
   *   database     =>  mysql_database,
   *   encoding     =>  mysql_encoding,
   *   table_prefix =>  mysql_table_prefix
   * );</pre>
   */
  function __construct(array $opts){
    $connect = $this->connect($opts);
    $encoding = $this->set_encoding($opts['encoding']);
    $select_db = $this->select_db($opts['database']);
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }


  /**
   * Connect to database
   * @access private
   * @param array $opts
   * @return bool
   */
  protected function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    $this->resource_ID = $connection;
    $this->options = $opts;
    return (bool) $connection or self::error("Connection to host '".$opts['host']."' failed");
  }


  /**
   * Sets table names/encoding
   * @access private
   * @param string $encoding
   * @return bool
   */
  protected function set_encoding($encoding){
    if($encoding){
      $query = new NeevoMySQLQuery($this);
      $query->sql("SET NAMES $encoding");
      return (bool) $query->run();
    } else return true;
  }


  /**
   * Selects database to use
   * @access private
   * @param string $db_name
   * @return bool
   */
  protected function select_db($db_name){
    $select = @mysql_select_db($db_name, $this->resource_ID);
    return (bool) $select or $this->error("Failed selecting database '$db_name'");
  }


  /**
   * Sets and/or returns table prefix
   * @param string $prefix Table prefix to set
   * @return mixed
   */
  public function prefix($prefix = null){
    if(isset($prefix)) $this->table_prefix = $prefix;
    return $this->table_prefix;
  }


  /**
   * Sets and/or returns error-reporting
   * @param bool $value Boolean value of error-reporting
   * @return bool
   */
  public function errors($value = null){
    if(isset($value)) $this->error_reporting = $value;
    return $this->error_reporting;
  }


  /**
   * Performs Query
   * @access private
   * @param string $query Query to perform
   * @return resource
   */
  public final function query($query){
    $q = @mysql_query($query, $this->resource_ID);
    $this->queries++;
    $this->last=$query;
    if($q) return $q;
    else return $this->error('Query failed');
  }


  /**
   * Creates NeevoMySQLQuery object for SELECT query
   * @param mixed $columns Array or comma-separated list of columns to select
   * @param string $table Database table to use for selecting
   * @return NeevoMySQLQuery
   */
  public final function select($columns, $table){
    $q = new NeevoMySQLQuery($this, 'select', $table);
    $q->cols($columns);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for INSERT query
   * @param string $table Database table to use for inserting
   * @param array $data Associative array of values to insert in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function insert($table, array $data){
    $q = new NeevoMySQLQuery($this, 'insert', $table);
    $q->data($data);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for UPDATE query
   * @param string $table Database table to use for updating
   * @param array $data Associative array of values for update in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function update($table, array $data){
    $q = new NeevoMySQLQuery($this, 'update', $table);
    $q->data($data);
    return $q;
  }


  /**
   * Creates NeevoMySQLQuery object for DELETE query
   * @param string $table Database table to use for deleting
   * @return NeevoMySQLQuery
   */
  public final function delete($table){
    return new NeevoMySQLQuery($this, 'delete', $table);
  }


  /** 
   * Fetches data from given Query resource
   * @param string $query MySQL (SELECT) query
   * @param int $type Result data as: Possible values:<ul>
   *  <li>Neevo::ASSOC  (1) - fetches an array with associative arrays as table rows</li>
   *  <li>Neevo::NUM    (2) - fetches an array with numeric arrays as table rows</li>
   *  <li>Neevo::OBJECT (3) - fetches an array with objects as table rows</li></ul>
   * @return array Associative array built from query
   */
  public function fetch($query, $type=1){
    $arr=array();
    if($type==1){ // Assoc
      while($tmp_arr=@mysql_fetch_assoc($query)){
        $arr[]=$tmp_arr;
      }
    }
    if($type==2){ // Numeric
      while($tmp_arr=@mysql_fetch_row($query)){
        $arr[]=$tmp_arr;
      }
    }
    if($type==3){ // Object
      while($tmp_arr=@mysql_fetch_object($query)){
        $arr[]=$tmp_arr;
      }
    }
    // Only 1 row
    if(count($arr)==1){
      $arr = $arr[0];
      // Only 1 column
      if(count($arr)==1){
        $result = array_values($arr);
        $arr = $result[0];
      }
    }
    return $query ? $arr : $this->error("Fetching result data failed");
    @mysql_free_result($query);
  }


  /**
   * Generates E_USER_WARNING
   * @access private
   * @param string $err_neevo
   * @return false
   */
  public function error($err_neevo){
    $err_string = mysql_error();
    $err_no = mysql_errno();
    $err = "<b>Neevo error</b> ($err_no) - ";
    $err .= $err_neevo;
    $err_string = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $err_string);
    $err .= ". $err_string";
    if($this->errors()) trigger_error($err, E_USER_WARNING);
    return false;
  }

  /**
   * Returns some info about MySQL connection as an array or string
   * @param bool $return_string Return as a string or not (default: no)
   * @param bool $html Use HTML (for highlighting, etc.) or not (default: no)
   * @return mixed
   */
  public function info($return_string = false, $html = false){
    $info = $this->options;
    unset($info['password']);
    $info['queries'] = $this->queries;
    $info['last'] = $html ? NeevoStatic::highlight_sql($this->last) : $this->last;
    $info['table_prefix'] = $this->prefix();
    $info['error_reporting'] = $this->errors();
    $info['memory_usage'] = $this->memory();

    if(!$return_string) return $info;
    else{
      $er = array('off', 'on');

      if($html){
        $ot = "<strong>";
        $ct = "</strong>";
      }
      
      $string = " Connected to database $ot'{$info['database']}'$ct on $ot{$info['host']}$ct as $ot{$info['username']}$ct user\n"
      . "$ot Database encoding:$ct {$info['encoding']}\n"
      . "$ot Table prefix:$ct {$info['table_prefix']}\n"
      . "$ot Error-reporting:$ct {$er[$info['error_reporting']]}\n"
      . "$ot Executed queries:$ct {$info['queries']}\n"
      . "$ot Last executed query:$ct {$info['last']}\n"
      . "$ot Script memory usage:$ct {$info['memory_usage']}\n";

      return $html ? NeevoStatic::nlbr($string) : $string;
    }
  }


  /**
   * Returns script memory usage
   * @return string
   */
  public function memory(){
    return NeevoStatic::filesize(memory_get_usage(true));
  }

  /**
   * Sets logging to file for Neevo
   * @param bool $state Log queries to file or not (true/false)
   * @param string $filename Path to file for logging (directory must be writeable). Default: "./neevo.log"
   * @return bool
   */
  public function log($state, $filename = './neevo.log'){
    if($state==true){
      $this->logging = true;
      if($filename){
        $this->log_file = $filename;
        return true;
      }
      else return $this->error('Log file must be set!');
    }
    else{
      $this->logging = false;
      return true;
    }
  }


  /**
   * Adds record to log-file.
   * @access private
   * @param string $query
   * @param float $exectime
   * @param string $affrows
   * @return bool
   */
  public function add_log($query, $exectime, $affrows){
    if($this->logging && $this->log_file){
      setlocale(LC_TIME, "en_US");
      $time = strftime("%a %d/%b/%Y %H:%M:%S %z");
      $ip = $_SERVER["REMOTE_ADDR"];
      $log = "[$time] [client $ip] [$query] [$exectime sec] [$affrows]\n";
      return NeevoStatic::write_file($this->log_file, $log);
    }
    else return false;
  }

}




/** Neevo class for MySQL query abstraction
 * @package Neevo
 */
class NeevoMySQLQuery {

  private $q_table, $q_type, $q_limit, $q_offset, $neevo, $q_resource, $q_time, $q_sql;
  private $q_where, $q_order, $q_columns, $q_data = array();


  /**
   * Query base constructor
   * @param array $object Reference to instance of Neevo class which initialized Query.
   * @param string $type Query type. Possible values: select, insert, update, delete
   * @param string $table Table to interact with
   */
  function  __construct(Neevo $object, $type = '', $table = ''){
    $this->neevo = $object;

    $this->type($type);
    $this->table($table);
  }


  /**
   * Sets table to interact
   * @param string $table
   * @return NeevoMySQLQuery
   */
  public function table($table){
    $this->q_table = $table;
    return $this;
  }


  /**
   * Sets query type. Possibe values: select, insert, update, delete
   * @param string $type
   * @return NeevoMySQLQuery
   */
  public function type($type){
    $this->q_type = $type;
    return $this;
  }


  /**
   * Method for running direct SQL code.
   * @param string $sql Direct SQL code
   * @return NeevoMySQLQuery
   */
  public function sql($sql){
    $this->q_sql = $sql;
    return $this;
  }


  /**
   * Sets columns to retrive in SELECT queries
   * @param mixed $columns Array or comma-separated list of columns.
   * @return NeevoMySQLQuery
   */
  public function cols($columns){
    if(!is_array($columns)) $columns = explode(',', $columns);
    $cols = array();
    foreach ($columns as $col) {
      $col = trim($col);
      if($col!='*'){
        if(NeevoStatic::is_as_construction($col)) $col = NeevoStatic::escape_as_construction($col);
        elseif(NeevoStatic::is_sql_function($col)) $col = NeevoStatic::escape_sql_function($col);
        else $col = "`$col`";
      }
      $cols[] = $col;
    }
    $this->q_columns = $cols;
    return $this;
  }


  /**
   * Data for INSERT and UPDATE queries
   * @param array $data Data in format "$column=>$value"
   * @return NeevoMySQLQuery
   */
  public function data(array $data){
    $this->q_data = $data;
    return $this;
  }


  /**
   * Sets WHERE condition for Query
   * @param string $where Column to use and optionaly operator: "email LIKE" or "email !="
   * @param string $value Value to search for: "%@example.%" or "spam@example.com"
   * @param string $glue Operator (AND, OR, etc.) to use betweet this and next WHERE condition
   * @return NeevoMySQLQuery
   */
  public function where($where, $value, $glue = null){

    $where_condition = explode(' ', $where);
    if(!isset($where_condition[1])) $where_condition[1] = '=';
    $column = $where_condition[0];

    if(NeevoStatic::is_sql_function($column)) $column = NeevoStatic::escape_sql_function($column);
    else $column = "`$column`";

    $value = NeevoStatic::escape_string($value);

    $condition = array($column, $where_condition[1], $value, strtoupper($glue));

    $this->q_where[] = $condition;

    return $this;
  }


  /**
   * Sets ORDER BY rule for Query
   * @param string $args [Infinite arguments] Order rules: "col_name ASC", "col_name" or "col_name DESC", etc...
   * @return NeevoMySQLQuery
   */
  public function order($args){
    $rules = array();
    $arguments = func_get_args();
    foreach ($arguments as $argument) {
      $order_rule = explode(' ', $argument);
      $order_rule[0] = "`".$order_rule[0]."`";
      $rules[] = $order_rule;
    }
    $this->q_order = $rules;

    return $this;
  }


  /**
   * Sets limit (and offset) for Query
   * @param int $limit Limit
   * @param int $offset Offset
   * @return NeevoMySQLQuery
   */
  public function limit($limit, $offset = null){
    $this->q_limit = $limit;
    if(isset($offset) && $this->q_type=='select') $this->q_offset = $offset;
    return $this;
  }


  /**
   * Prints consequential Query (highlighted by default)
   * @param bool $color Highlight query or not (default: yes)
   * @param bool $return_string Return the string or not (default: no)
   */
  public function dump($color = true, $return_string = false){
    $code = $color ? NeevoStatic::highlight_sql($this->build()) : $this->build();
    if(!$return_string) echo $code;
    return $return_string ? $code : $this;
  }


  /**
   * Performs Query
   * @return resource
   */
  public function run(){
    $start = explode(" ", microtime());
    $query = $this->neevo->query($this->build());
    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time($time);
    $this->q_resource = $query;
    $this->neevo->add_log($this->build(), $time, $this->rows(true));
    return $query;
  }


  /**
   * Shorthand for NeevoMySQLQuery->run() and Neevo->fetch()
   * @param int $type Result data as: Possible values:<ul>
   *  <li>Neevo::ASSOC  (1) - fetches an array with associative arrays as table rows</li>
   *  <li>Neevo::NUM    (2) - fetches an array with numeric arrays as table rows</li>
   *  <li>Neevo::OBJECT (3) - fetches an array with objects as table rows</li></ul>
   * @return mixed Array or string (if only one value is returned).
   */
  public function fetch($type = 1){
    $resource = is_resource($this->q_resource) ? $this->q_resource : $this->run();
    return $this->neevo->fetch($resource, $type);
  }


  /**
   * Move internal result pointer
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($row_number){
    if(!is_resource($this->q_resource)) $this->run();
    
    $seek = @mysql_data_seek($this->q_resource, $row_number);
    return $seek ? $seek : $this->error("Cannot seek to row $row_number");
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param bool $string Return rows as a string ("Rows: 5", "Affected: 10"). Default: FALSE
   * @return mixed Number of rows (int) or FALSE if used on invalid query.
   */
  public function rows($string = false){
    if($this->q_type!='select') $aff_rows = $this->time() ? @mysql_affected_rows($this->neevo->resource_ID) : false;
    else $num_rows = @mysql_num_rows($this->q_resource);
    
    if($num_rows || $aff_rows){
      if($string){
        return $num_rows ? "Rows: $num_rows" : "Affected: $aff_rows";
      }
      else return $num_rows ? $num_rows : $aff_rows;
    }
    else return false;
  }


  /**
   * Sets and/or returns Execution time of Query
   * @param int $time Time value to set.
   * @return int Query execution time
   */
  public function time($time = null){
    if(isset($time)) $this->q_time = $time;
    return $this->q_time;
  }

  /**
   * Returns some info about this Query as an array or string
   * @param bool $return_string Return as a string or not (default: no)
   * @param bool $html Use HTML or not (default: no)
   * @return mixed Info about Query
   */
  public function info($return_string = false, $html = false){
    $noexec = 'not yet executed';
    $noselect = 'not SELECT query';

    $exec_time = $this->time() ? $this->time() : -1;
    $rows = $this->time() ? $this->rows() : -1;

    $info = array(
      'resource' => $this->neevo->resource_ID,
      'query' => $this->dump($html, true),
      'exec_time' => $exec_time,
      'rows' => $rows
    );

    if($this->q_type == 'select') $info['query_resource'] = $this->q_resource;

    if(!$return_string) return $info;
    
    else{
      if($info['exec_time']==-1) $info['exec_time'] = $noexec;
      else $info['exec_time'] .= " seconds";

      if($info['rows']==-1) $info['rows'] = $noexec;
      $rows_prefix = ($this->q_type != 'select') ? "Affected" : "Fetched";

      $query_resource = ($this->q_type == 'select') ? $info['query_resource'] : $noselect;

      if($html){
        $ot = "<strong>";
        $ct = "</strong>";
      }

      $string = "$ot Query-string:$ct {$info['query']}\n"
      . "$ot Resource:$ct {$info['resource']}\n"
      . "$ot Query resource:$ct $query_resource\n"
      . "$ot Execution time:$ct {$info['exec_time']}\n"
      . "$ot $rows_prefix rows:$ct {$info['rows']}\n";

      return $html ? NeevoStatic::nlbr($string) : $string;
    }

  }


  /**
   * Unsets defined parts of Query (WHERE conditions, ORDER BY clauses, affected columns (INSERT, UPDATE), LIMIT, etc.).
   *
   * <p>To unset 2nd WHERE condition from Query: <code>SELECT * FROM table WHERE id=5 OR name='John Doe' OR ...</code> use following: <code>$select->undo('where', 2);</code></p>
   * <p>To unset 'name' column from Query: <code>UPDATE table SET name='John Doe', id=4 WHERE ...</code> use following: <code>$update->undo('value', 'name');</code></p>
   *
   * @param string $sql_part Part of Query to unset. Possible values are: (string)
   * <ul>
     * <li>where (for WHERE conditions)</li>
     * <li>order (for ORDER BY clauses)</li>
     * <li>column (for selected columns in SELECT queries)</li>
     * <li>value (for values to put/set in INSERT and UPDATE)</li>
     * <li>limit (for LIMIT clause)</li>
     * <li>offset (for OFFSET clause)</li>
   * </ul>
   * @param mixed $position Exact piece of Query part. This can be:
   * <ul>
     * <li>(int) Ordinal number of Query part piece (WHERE condition, ORDER BY clause, columns in SELECT queries) to unset.</li>
     * <li>(string) Column name from defined values (values to put/set in INSERT and UPDATE queries) to unset.</li>
     * <li>(array) Array of options (from pevious two) if you want to unset more than one piece of Query part (e.g 2nd and 3rd WHERE condition).</li>
   * </ul>
   * This argument is not required for LIMIT & OFFSET. Default is (int) 1. See example.
   * @return NeevoMySQLQuery
   */
  public function undo($sql_part, $position = 1){
    switch (strtolower($sql_part)) {
      case 'where':
        $part = 'where';
        break;

      case 'order';
      case 'order-by';
      case 'order by';
        $part = 'order';
        break;

      case 'column';
      case 'columns';
      case 'cols';
      case 'col';
        $part = 'columns';
        break;

      case 'data';
      case 'value';
      case 'values';
        $part = 'data';
        break;

      case 'limit':
        $part = 'limit';
        $str = true;
        break;

      case 'offset':
        $part = 'offset';
        $str = true;
        break;

      default:
        $this->neevo->error("Undo failed: No such Query part '$sql_part' supported for undo()");
        break;
    }
    
    $part = "q_$part";

    if($str){

      unset($this->$part);
    }
    else{
      if(isset($this->$part)){
        $positions = array();
        if(!is_array($position)) $positions[] = $position;
        foreach ($positions as $pos) {
          $pos = is_numeric($pos) ? $pos-1 : $pos;
          $apart = $this->$part;
          unset($apart[$pos]);
          foreach($apart as $key=>$value){
            $loop[$key] = $value;
          }
          $this->$part = $loop;
        }
      } else $this->neevo->error("Undo failed: No such Query part '$sql_part' for this kind of Query");
    }

    return $this;
  }


  /**
   * Builds Query from NeevoMySQLQuery instance
   * @return string the Query
   */
  public function build(){

    if($this->q_sql){
      $q = $this->q_sql;
    }
    else{

      $table = $this->neevo->prefix().$this->q_table;

      // WHERE statements
      if($this->q_where){
        $wheres = array();
        $wheres2 = array();
        $wheres3 = array();

        // Set ADN glue for queries without glue defined
        foreach ($this->q_where as $in_where) {
          if($in_where[3]=='') $in_where[3] = 'AND';
          $wheres[] = $in_where;
        }

        // Unset glue for last WHERE clause (defind by former loop)
        unset($wheres[count($wheres)-1][3]);

        // Join WHERE clause array to one string
        foreach ($wheres as $in_where2) {
          $wheres2[] = join(' ', $in_where2);
        }
        // Remove some spaces
        foreach ($wheres2 as $rplc_where){
          $wheres3[] = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
        }

        $where = " WHERE ".join(' ', $wheres3);
      }

      // INSERT data
      if($this->q_type == 'insert' && $this->q_data){
        $icols=array();
        $ivalues=array();
        foreach(NeevoStatic::escape_array($this->q_data) as $col => $value){
          $icols[]="`$col`";
          $ivalues[]=$value;
        }
        $insert_data = " (".join(', ',$icols).") VALUES (".join(', ',$ivalues).")";
      }

      // UPDATE data
      if($this->q_type == 'update' && $this->q_data){
        $update=array();
        foreach(NeevoStatic::escape_array($this->q_data) as $col => $value){
          $update[]="`$col`=$value";
        }
        $update_data = " SET " . join(', ', $update);
      }

      // ORDER BY statements
      if($this->q_order){
        $orders = array();
        foreach($this->q_order as $in_order) {
          $orders[] = join(' ', $in_order);
        }
        $order = " ORDER BY ".join(', ', $orders);
      }

      // LIMIT & OFFSET
      if($this->q_limit) $limit = " LIMIT ".$this->q_limit;
      if($this->q_offset) $limit .= " OFFSET ".$this->q_offset;


      // SELECT query
      if($this->q_type == 'select'){
        $q .= 'SELECT ';
        $q .= join(', ', $this->q_columns);
        $q .= ' FROM `'.$table.'`';
        $q .= $where . $order . $limit;
      }

      // INSERT query
      if($this->q_type == 'insert'){
        $q .= 'INSERT INTO `' . $table . '`';
        $q .= $insert_data;
      }

      // UPDATE query
      if($this->q_type == 'update'){
        $q .= 'UPDATE `' . $table .'`';
        $q .= $update_data . $where . $order . $limit;
      }

      // DELETE query
      if($this->q_type == 'delete'){
        $q .= 'DELETE FROM `' . $table . '`';
        $q .= $where . $order . $limit;
      }
    }

    return "$q;";

  }

}



/** Main Neevo class for some additional static methods
 * @package Neevo
 */
class NeevoStatic extends Neevo {

  protected static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK');

  /** Highlights given MySQL query */
  public static function highlight_sql($sql){
    $chcolors = array('chars'=>'black','keywords'=>'green','joins'=>'grey','functions'=>'green','constants'=>'red');
    $hcolors = self::$highlight_colors;
    unset($hcolors['columns']);unset($hcolors['background']);

    $words = array('keywords'=>array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins' => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars' => '/([\\.,;!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i');

    $sql=str_replace('\\\'','\\&#039;',$sql);

    foreach($chcolors as $key=>$color){
      $regexp=in_array($key,array('constants','chars')) ? $words[$key] : '/\\b('.join("|",$words[$key]).')\\b/i';
      $sql=preg_replace($regexp, "<span style=\"color:$color\">$1</span>",$sql);
    }

    $sql = str_replace($chcolors, $hcolors, $sql);
    return "<code style=\"color:".self::$highlight_colors['columns'].";background:".self::$highlight_colors['background']."\"> $sql </code>\n";
  }

  /** Escapes whole array for use in MySQL */
  public static function escape_array(array $array){
    $result=array();
    foreach($array as $key => $value){
       $result[$key] = is_numeric($value) ? $value : ( is_string($value) ? self::escape_string($value) : ( is_array($value) ? self::escape_array($value) : $value ) );
    }
    return $result;
  }

  /** Escapes given string for use in MySQL */
  public static function escape_string($string){
    $string=str_replace('\'', '\\\'' ,$string);
    return is_string($string) ? ( self::is_sql_function($string) ? self::escape_sql_function($string) : "'$string'" ) : $string;
  }

  /** Checks whether a given string is a SQL function or not */
  public static function is_sql_function($string){
    if(is_string($string)){
      $is_plmn = preg_match("/^(\w*)(\+|-)(\w*)/", $string);
      $var = is_string($string) ? strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/','',$string)) : false;
      $is_sql = in_array(preg_replace('/\(.*\)/','',$var), self::$sql_functions);
      return ($is_sql || $is_plmn) ? true : false;
    }
    else return false;

  }

  /** Escapes given SQL function  */
  public static function escape_sql_function($sql_function){
    return str_replace(array('("','")'), array('(\'','\')'), $sql_function);
  }

  /** Checks whether a given string is a MySQL 'AS construction' ([SELECT] cars AS vegetables) */
  public static function is_as_construction($string){
    return is_string($string) ? ( preg_match('/(.*) as \w*/i', $string) ? true : false ) :  false;
  }

  /** Escapes (quotes column name with ``) given 'AS construction' */
  public static function escape_as_construction($as_construction){
    $construction=explode(' ', $as_construction);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0]="`".$construction[0]."`";
    }
    $as_construction=join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS `$3`',$as_construction);
  }

  public static function nlbr($string){
    $string = str_replace("\n\n", "\n", $string);
    return str_replace("\n","<br>", $string);
  }

  /** Returns content of the file */
  public static function read_file($filename) {
    if (!function_exists('file_get_contents')) {
      $fhandle = fopen($filename, "r");
      $fcontents = fread($fhandle, filesize($filename));
      fclose($fhandle);
    }
    else $content = file_get_contents($filename);

    return $content;
  }

  /** Puts content to the file */
  public static function write_file($filename, $data) {
    $f = @fopen($filename, 'a+');
    if (!$f) return false;
    else {
      $content = fwrite($f, $data);
      fclose($f);
    }

    return $content;
  }

  /** Returns formatted filesize */
  public static function filesize($bytes){
    $unit=array('B','kB','MB','GB','TB','PB');
    return @round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$unit[$i];
  }

}

?>
