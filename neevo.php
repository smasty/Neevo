<?php
/**
 * Neevo - Tiny open-source MySQL layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
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


/**
 * Main Neevo layer class
 * @package Neevo
 */
class Neevo{

  // Fields
  private $resource, $last, $table_prefix, $queries, $error_reporting;
  private $options = array();

  // mysql-fetch types
  const ASSOC  = 11;
  const OBJECT = 12;

  // Error-reporting levels
  const E_NONE    = 21;
  const E_CATCH   = 22;
  const E_WARNING = 23;
  const E_STRICT  = 24;

  // Quoting & escaping characters
  const COL_QUOTE = '`';
  const ESCAPE_CHAR  = "\\";


  /**
   * Neevo main class.
   * @param array $opts Array of options in following format:
   * <pre>Array(
   *   host            =>  mysql_host,
   *   username        =>  mysql_username,
   *   password        =>  mysql_password,
   *   database        =>  mysql_database,
   *   encoding        =>  mysql_encoding,
   *   table_prefix    =>  mysql_table_prefix
   *   error_reporting =>  error_reporting_level; See error_reporting() for possible values.
   * );</pre>
   * @see Neevo::error_reporting(), Neevo::prefix()
   */
  public function __construct(array $opts){
    $this->connect($opts);
    if($opts['error_reporting']) $this->error_reporting = $opts['error_reporting'];
    if($opts['table_prefix']) $this->table_prefix = $opts['table_prefix'];
  }


  /**
   * Closes connection to MySQL server.
   */
  public function  __destruct(){
    mysql_close($this->resource);
  }

  
  /**
   * Connects to database server, selects database and sets encoding (if defined)
   * @access private
   * @param array $opts
   * @return bool
   */
  protected function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    if(!is_resource($connection) or !$this->test_connection($connection)) $this->error("Connection to host '".$opts['host']."' failed");
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->error("Could not select database '{$opts['database']}'");
    }
    $this->resource = $connection;
    $this->options = $opts;

    if($opts['encoding']){
      try{
        $e = mysql_query("SET NAMES ".$opts['encoding']);
      }
      catch (NeevoException $e){}
    }
    return (bool) $connection;
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
   * @param int $value Value of error-reporting.
   * Possible values:
   * <ul><li>Neevo::E_NONE: Turns Neevo error-reporting off</li>
   * <li>Neevo::E_CATCH: Catches all Neevo exceptions by default handler</li>
   * <li>Neevo::E_WARNING: Catches only Neevo warnings</li>
   * <li>Neevo::E_STRICT: Catches no Neevo exceptions</li></ul>
   * @return int
   */
  public function error_reporting($value = null){
    if(isset($value)) $this->error_reporting = $value;
    return $this->error_reporting;
  }


  /**
   * Sets and/or returns last executed query
   * @param NeevoMySQLQuery $last Last executed query
   * @return NeevoMySQLQuery
   */
  public function last(NeevoMySQLQuery $last = null){
    if($last instanceof NeevoMySQLQuery) $this->last = $last;
    return $this->last;
  }


  public function queries($val = null){
    if(is_numeric($val)) $this->queries += $val;
    return $this->queries;
  }


  /**
   * Returns resource identifier
   * @return resource
   */
  public function resource(){
    return $this->resource;
  }


  /**
   * Tests if connection is usable (wrong username without password)
   * @access private
   * @param resource $resource
   * @return bool
   */
  private function test_connection($resource){
    try{
      $q = mysql_query("SHOW DATABASES", $resource);
      $r = $this->fetch($q);
      if(!$r || $r == "information_schema") throw new NeevoException("Error!");
    }
    catch (NeevoException $e){return false;}
    return true;
  }


  /**
   * Fetches data
   * @param resource $resource
   * @param int $type
   * @return mixed
   * @see NeevoMySQLQuery::fetch()
   */
  public function fetch($resource, $type = Neevo::ASSOC){
    $rows = array();
    if($type == Neevo::ASSOC){
        while($tmp_rows = @mysql_fetch_assoc($resource))
          $rows[] = (count($tmp_rows) == 1) ? $tmp_rows[max(array_keys($tmp_rows))] : $tmp_rows;
    } elseif ($type == Neevo::OBJECT){
        while($tmp_rows = @mysql_fetch_object($resource)){
          $obj_vars = get_object_vars($tmp_rows);
          $rows[] = (count($obj_vars) == 1) ? $obj_vars[max(array_keys($obj_vars))] : $tmp_rows;
        }
    } else $this->error("Fetching result data failed");

    if(count($rows) == 1){
      $rows = $rows[0];
    }
    if(!count($rows)) $rows = false; // Empty
    mysql_free_result($resource);
    return $rows;
  }


  /**
   * Performs Query
   * @access private
   * @param NeevoMySQLQuery $query Query to perform.
   * @param bool $catch_error Catch exception by default if mode is not E_STRICT
   * @return resource
   */
  public final function query(NeevoMySQLQuery $query, $catch_error = false){
    $q = @mysql_query($query->build(), $this->resource);
    $this->queries(1);
    $this->last($query);
    if($q) return $q;
    else return $this->error('Query failed', $catch_error);
  }


  /**
   * Creates NeevoMySQLQuery object for SELECT query
   * @param mixed $columns Array or comma-separated list of columns to select
   * @param string $table Database table to use for selecting
   * @return NeevoMySQLQuery
   */
  public final function select($columns, $table){
    $q = new NeevoMySQLQuery($this, 'select', $table);
    return $q->cols($columns);
  }


  /**
   * Creates NeevoMySQLQuery object for INSERT query
   * @param string $table Database table to use for inserting
   * @param array $data Associative array of values to insert in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function insert($table, array $data){
    $q = new NeevoMySQLQuery($this, 'insert', $table);
    return $q->data($data);
  }


  /**
   * Creates NeevoMySQLQuery object for UPDATE query
   * @param string $table Database table to use for updating
   * @param array $data Associative array of values for update in format column_name=>column_value
   * @return NeevoMySQLQuery
   */
  public final function update($table, array $data){
    $q = new NeevoMySQLQuery($this, 'update', $table);
    return $q->data($data);
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
   * If error_reporting is turned on, throws NeevoException available to catch.
   * @access private
   * @param string $neevo_msg Error message
   * @return false
   */
  public function error($neevo_msg, $catch = false){
    $mysql_msg = mysql_error();
    $no = mysql_errno();
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);
    $msg = "$neevo_msg. $mysql_msg";
    $mode = $this->error_reporting();
    if($mode != self::E_NONE){
      if(($mode != self::E_STRICT && $catch) || $mode == self::E_CATCH){
        try{
          throw new NeevoException($msg);
        } catch (NeevoException $e){
          echo "<b>Catched NeevoException:</b> ".$e->getMessage()."\n";
        }
      }
      else throw new NeevoException($msg);
    }
    return false;
  }

  /**
   * Returns some info about MySQL connection as an array
   * @return array
   */
  public function info(){
    $info = $this->options;
    unset($info['password']);
    $info['queries'] = $this->queries();
    $info['last'] = $this->last();
    $info['table_prefix'] = $this->prefix();
    $info['error_reporting'] = $this->error_reporting();
    $info['memory_usage'] = $this->memory();

    return $info;
  }


  /**
   * Returns script memory usage
   * @return string
   */
  public function memory(){
    return NeevoStatic::filesize(memory_get_usage(true));
  }

}




/**
 * Neevo class for MySQL query abstraction
 * @package Neevo
 */
class NeevoMySQLQuery {

  private $table, $type, $limit, $offset, $neevo, $resource, $time, $sql;
  private $where, $order, $columns, $data = array();


  /**
   * Query base constructor
   * @param array $object Reference to instance of Neevo class which initialized Query
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
    $this->table = $table;
    return $this;
  }


  /**
   * Sets query type. Possibe values: select, insert, update, delete
   * @param string $type
   * @return NeevoMySQLQuery
   */
  public function type($type){
    $this->type = $type;
    return $this;
  }


  /**
   * Method for running direct SQL code
   * @param string $sql Direct SQL code
   * @return NeevoMySQLQuery
   */
  public function sql($sql){
    $this->sql = $sql;
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
    $this->columns = $columns;
    return $this;
  }


  /**
   * Data for INSERT and UPDATE queries
   * @param array $data Data in format "$column=>$value"
   * @return NeevoMySQLQuery
   */
  public function data(array $data){
    $this->data = $data;
    return $this;
  }


  /**
   * Sets WHERE condition for Query
   *
   * <p>Supports LIKE and IN functions</p>
   *
   * @param string $where Column to use and optionaly operator/function: "email !=", "email LIKE" or "email IN".
   * @param mixed $value Value to search for: "spam@foo.com", "%@foo.com" or array('john@foo.com', 'doe@foo.com', 'john.doe@foo.com')
   * @param string $glue Glue (AND, OR, etc.) to use betweet this and next WHERE condition. If not set, AND will be used.
   * @return NeevoMySQLQuery
   */
  public function where($where, $value, $glue = null){
    $where_condition = explode(' ', $where);
    if(is_null($value)){
      $where_condition[1] = "IS";
      $value = "NULL";
    }
    if(is_array($value)) $where_condition[1] = "IN";
    if(!isset($where_condition[1])) $where_condition[1] = '=';
    $column = $where_condition[0];
    $condition = array($column, $where_condition[1], $value, strtoupper($glue));
    $this->where[] = $condition;
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
      $rules[] = $order_rule;
    }
    $this->order = $rules;
    return $this;
  }


  /**
   * Sets limit (and offset) for Query
   * @param int $limit Limit
   * @param int $offset Offset
   * @return NeevoMySQLQuery
   */
  public function limit($limit, $offset = null){
    $this->limit = $limit;
    if(isset($offset) && $this->type == 'select') $this->offset = $offset;
    return $this;
  }


  /**
   * Prints consequential Query (highlighted by default)
   * @param bool $color Highlight query or not (default: yes)
   * @param bool $return_string Return the string or not (default: no)
   * @return NeevoMySQLQuery
   */
  public function dump($color = true, $return_string = false){
    $code = $color ? NeevoStatic::highlight_sql($this->build()) : $this->build();
    if(!$return_string) echo $code;
    return $return_string ? $code : $this;
  }


  /**
   * Performs Query
   * @param bool $catch_error Catch exception by default if mode is not E_STRICT
   * @return resource
   */
  public function run($catch_error = false){
    $start = explode(" ", microtime());
    $query = $this->neevo->query($this, $catch_error);
    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time($time);
    $this->resource = $query;
    return $query;
  }


  /**
   * Fetches data from given Query resource and executes query (if it haven't already been executed)
   * @param int $type Table rows are represented as: (possible values)<ul>
   *  <li>Neevo::ASSOC  - Associative arrays</li>
   *  <li>Neevo::OBJECT - Objects</li></ul>
   *  Default is Neevo::ASSOC. If only one column is returned in each row, row is represented as
   *  a value of that column (string, number, etc.), not an array with only one element.
   * @return mixed Array or string (if only one value is returned) or FALSE (if nothing is returned).
   */
  public function fetch($type = Neevo::ASSOC){
    $resource = is_resource($this->resource) ? $this->resource : $this->run();
    $rows = $this->neevo->fetch($resource, $type);
    return $resource ? $rows : $this->neevo->error("Fetching result data failed");
  }


  /**
   * Move internal result pointer
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($row_number){
    if(!is_resource($this->resource)) $this->run();

    $seek = @mysql_data_seek($this->resource, $row_number);
    return $seek ? $seek : $this->neevo->error("Cannot seek to row $row_number");
  }


  /**
   * Randomize result order. (Shorthand for NeevoMySQLQuery->order('RAND()');)
   * @return NeevoMySQLQuery
   */
  public function rand(){
    $this->order('RAND()');
    return $this;
  }


  /**
   * Returns number of affected rows for INSERT/UPDATE/DELETE queries and number of rows in result for SELECT queries
   * @param bool $string Return rows as a string ("Rows: 5", "Affected: 10"). Default: FALSE
   * @return mixed Number of rows (int) or FALSE if used on invalid query.
   */
  public function rows($string = false){
    if($this->type!='select') $aff_rows = $this->time() ? @mysql_affected_rows($this->neevo->resource()) : false;
    else $num_rows = @mysql_num_rows($this->resource);

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
    if(isset($time)) $this->time = $time;
    return $this->time;
  }


  /**
   * Returns some info about this Query as an array
   * @return array Info about Query
   */
  public function info(){
    $exec_time = $this->time() ? $this->time() : -1;
    $rows = $this->time() ? $this->rows() : -1;
    $info = array(
      'resource' => $this->neevo->resource(),
      'query' => $this->dump($html, true),
      'exec_time' => $exec_time,
      'rows' => $rows
    );
    if($this->type == 'select') $info['query_resource'] = $this->resource;
    return $info;
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
   * This argument is not required for LIMIT & OFFSET. Default is (int) 1.
   * @return NeevoMySQLQuery
   */
  public function undo($sql_part, $position = 1){
    switch (strtolower($sql_part)) {
      case 'where':
        $part = 'where';
        break;
      case 'order';
        $part = 'order';
        break;
      case 'column';
        $part = 'columns';
        break;
      case 'value';
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
        $this->neevo->error("Undo failed: No such Query part '$sql_part' supported for undo()", true);
        break;
    }

    if($str)
      unset($this->$part);
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
      } else $this->neevo->error("Undo failed: No such Query part '$sql_part' for this kind of Query", true);
    }
    return $this;
  }


  /**
   * Builds table-name for queries
   * @return string
   */
  private function build_tablename(){
    $pieces = explode(".", $this->table);
    $prefix = $this->neevo->prefix();
    if($pieces[1])
      return Neevo::COL_QUOTE .$pieces[0] .Neevo::COL_QUOTE ."." .Neevo::COL_QUOTE .$prefix .$pieces[1] .Neevo::COL_QUOTE;
    else return Neevo::COL_QUOTE .$prefix .$pieces[0] .Neevo::COL_QUOTE;
  }


  /**
   * Builds WHERE statement for queries
   * @return string
   */
  private function build_where(){
    foreach ($this->where as $where) {
      if(empty($where[3])) $where[3] = 'AND';
      if(is_array($where[2])){
        $where[2] = "(" .join(", ", NeevoStatic::escape_array($where[2])) .")";
        $in_construct = true;
      }
      $wheres[] = $where;
    }
    unset($wheres[count($wheres)-1][3]);
    foreach ($wheres as $in_where) {
      $in_where[0] = (NeevoStatic::is_sql_func($in_where[0])) ? NeevoStatic::quote_sql_func($in_where[0]) : Neevo::COL_QUOTE .$in_where[0] .Neevo::COL_QUOTE;
      if(!$in_construct) $in_where[2] = NeevoStatic::escape_string($in_where[2]);
      $wheres2[] = join(' ', $in_where);
    }
    foreach ($wheres2 as &$rplc_where){
      $rplc_where = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
    }
    return " WHERE ".join(' ', $wheres2);
  }


  /**
   * Builds data part for INSERT queries ([INSERT INTO] (...) VALUES (...) )
   * @return string
   */
  private function build_insert_data(){
    foreach(NeevoStatic::escape_array($this->data) as $col => $value){
      $cols[] = Neevo::COL_QUOTE .$col .Neevo::COL_QUOTE;
      $values[] = $value;
    }
    return " (".join(', ',$cols).") VALUES (".join(', ',$values).")";
  }


  /**
   * Builds data part for UPDATE queries ([UPDATE ...] SET ...)
   * @return string
   */
  private function build_update_data(){
    foreach(NeevoStatic::escape_array($this->data) as $col => $value){
      $update[] = Neevo::COL_QUOTE .$col .Neevo::COL_QUOTE ."=" .$value;
    }
    return " SET " .join(', ', $update);
  }


  /**
   * Builds ORDER BY statement for queries
   * @return string
   */
  private function build_order(){
    foreach ($this->order as $in_order) {
      $in_order[0] = (NeevoStatic::is_sql_func($in_order[0])) ? $in_order[0] : Neevo::COL_QUOTE .$in_order[0] .Neevo::COL_QUOTE;
      $orders[] = join(' ', $in_order);
    }
    return " ORDER BY ".join(', ', $orders);
  }


  /**
   * Builds columns part for SELECT queries
   * @return string
   */
  private function build_select_cols(){
    foreach ($this->columns as $col) {
      $col = trim($col);
      if($col != '*'){
        if(NeevoStatic::is_as_constr($col)) $col = NeevoStatic::quote_as_constr($col);
        elseif(NeevoStatic::is_sql_func($col)) $col = NeevoStatic::quote_sql_func($col);
        else $col = Neevo::COL_QUOTE .$col .Neevo::COL_QUOTE;
      }
      $cols[] = $col;
    }
    return join(', ', $cols);
  }


  /**
   * Builds Query from NeevoMySQLQuery instance
   * @return string the Query
   */
  public function build(){

    if($this->sql)
      $q = $this->sql;

    else{
      $table = $this->build_tablename();

      if($this->where)
        $where = $this->build_where();

      if($this->order)
        $order = $this->build_order();
      
      if($this->limit) $limit = " LIMIT " .$this->limit;
      if($this->offset) $limit .= " OFFSET " .$this->offset;

      if($this->type == 'select'){
        $cols = $this->build_select_cols();
        $q .= "SELECT $cols FROM $table$where$order$limit";
      }

      if($this->type == 'insert' && $this->data){
        $insert_data = $this->build_insert_data();
        $q .= "INSERT INTO $table$insert_data";
      }

      if($this->type == 'update' && $this->data){
        $update_data = $this->build_update_data();
        $q .= "UPDATE $table$update_data$where$order$limit";
      }

      if($this->type == 'delete')
        $q .= "DELETE FROM $table$where$order$limit";
    }
    return "$q;";
  }

}



/**
 * Main Neevo class for some additional static methods
 * @package Neevo
 */
class NeevoStatic {

  private static $highlight_classes = array(
    'columns'    => 'sql-col',
    'chars'      => 'sql-char',
    'keywords'   => 'sql-kword',
    'joins'      => 'sql-join',
    'functions'  => 'sql-func',
    'constants'  => 'sql-const'
    );

  private static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK', 'RAND');

  /** Highlights given MySQL query */
  public static function highlight_sql($sql){
    $classes = self::$highlight_classes;
    unset($classes['columns']);

    $words = array(
      'keywords'  => array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins'     => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars'     => '/([\\.,!\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i'
    );

    $sql=str_replace('\\\'','\\&#039;', $sql);

    foreach($classes as $key => $class){
      $regexp = in_array( $key, array('constants', 'chars')) ? $words[$key] : '/\\b(' .join("|", $words[$key]) .')\\b/i';
      $sql = preg_replace($regexp, "<span class=\"$class\">$1</span>", $sql);
    }

    $sql = str_replace($chcolors, $hcolors, $sql);
    return "<code class=\"".self::$highlight_classes['columns']."\"> $sql </code>\n";
  }

  /** Escapes whole array for use in MySQL */
  public static function escape_array(array $array){
    foreach($array as &$value){
       $value = is_numeric($value) ? $value : ( is_string($value) ? self::escape_string($value) : ( is_array($value) ? self::escape_array($value) : $value ) );
    }
    return $array;
  }

  /** Escapes given string for use in MySQL */
  public static function escape_string($string){
    if(get_magic_quotes_gpc()) $string = stripslashes($string);
    $string = mysql_real_escape_string($string);
    return is_numeric($string) ? $string : ( is_string($string) ? ( self::is_sql_func($string) ? self::quote_sql_func($string) : "'$string'" ) : $string );
  }

  /** Checks whether a given string is a SQL function or not */
  public static function is_sql_func($string){
    if(is_string($string)){
      $is_plmn = preg_match("/^(\w*)(\+|-)(\w*)/", $string);
      $var = strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/', '', $string));
      $is_sql = in_array( preg_replace('/\(.*\)/', '', $var), self::$sql_functions);
      return ($is_sql || $is_plmn);
    }
    else return false;

  }

  /** Quotes given SQL function  */
  public static function quote_sql_func($sql_func){
    return str_replace(array('("', '")'), array('(\'', '\')'), $sql_func);
  }

  /** Checks whether a given string is a MySQL 'AS construction' ([SELECT] fruit AS vegetable) */
  public static function is_as_constr($string){
    return (bool) preg_match('/(.*) as \w*/i', $string);
  }

  /** Quotes given 'AS construction' */
  public static function quote_as_constr($as_constr){
    $construction = explode(' ', $as_constr);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0] = Neevo::COL_QUOTE .$construction[0] .Neevo::COL_QUOTE;
    }
    $as_constr = join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS ' .Neevo::COL_QUOTE .'$3' .Neevo::COL_QUOTE, $as_constr);
  }

  /** Returns formatted filesize */
  public static function filesize($bytes){
    $unit=array('B','kB','MB','GB','TB','PB');
    return @round($bytes/pow(1024, ($i = floor(log($bytes, 1024)))), 2).' '.$unit[$i];
  }

}

class NeevoException extends Exception{};

?>
