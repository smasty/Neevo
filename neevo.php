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

  var $resource;
  var $queries = 0;
  var $last = '';
  var $error_reporting = 1;
  var $table_prefix = '';
  private $options = array();


/*  CONSTANTS  */
  const ASSOC=1;
  const OBJECT=2;


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
    $this->resource = $connection;
    $this->options = $opts;
    if(!is_resource($connection)) throw new NeevoException("Connection to host '".$opts['host']."' failed");
    return (bool) $connection;
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
    $select = @mysql_select_db($db_name, $this->resource);
    if(!$select) throw new NeevoException("Failed selecting database '$db_name'");
    return $select;
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
  public function error_reporting($value = null){
    if(isset($value)) $this->error_reporting = $value;
    return $this->error_reporting;
  }

  /**
   * Performs Query
   * @access private
   * @param NeevoMySQLQuery $query Query to perform.
   * @return resource
   */
  public final function query(NeevoMySQLQuery $query){
    $q = @mysql_query($query->build(), $this->resource);
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
   * If error_reporting is turned on, throws NeevoException available to catch.
   * @access private
   * @param string $err_neevo
   * @return false
   */
  public function error($err_neevo){
    $err_string = mysql_error();
    $err_no = mysql_errno();
    $err = "Neevo error - ";
    $err .= $err_neevo;
    $err_string = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $err_string);
    $err .= ". $err_string";
    if($this->error_reporting()) throw new NeevoException($err);
    return false;
  }

  /**
   * Returns some info about MySQL connection as an array
   * @return array
   */
  public function info(){
    $info = $this->options;
    unset($info['password']);
    $info['queries'] = $this->queries;
    $info['last'] = $html ? NeevoStatic::highlight_sql($this->last->build()) : $this->last->build();
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




/** Neevo class for MySQL query abstraction
 * @package Neevo
 */
class NeevoMySQLQuery {

  private $table, $type, $limit, $offset, $neevo, $resource, $time, $sql;
  private $where, $order, $columns, $data = array();


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
   * Method for running direct SQL code.
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
   * @param string $where Column to use and optionaly operator: "email LIKE" or "email !="
   * @param string $value Value to search for: "%@example.%" or "spam@example.com"
   * @param string $glue Operator (AND, OR, etc.) to use betweet this and next WHERE condition
   * @return NeevoMySQLQuery
   */
  public function where($where, $value, $glue = null){

    $where_condition = explode(' ', $where);
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
    if(isset($offset) && $this->type=='select') $this->offset = $offset;
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
    $query = $this->neevo->query($this);
    $end = explode(" ", microtime());
    $time = round(max(0, $end[0] - $start[0] + $end[1] - $start[1]), 4);
    $this->time($time);
    $this->resource = $query;
    return $query;
  }


  /**
   * Fetches data from given Query resource and executes query (if it haven't already been executed);
   * @param int $type Return data as: (possible values)<ul>
   *  <li>Neevo::ASSOC  - Array of rows as associative arrays</li>
   *  <li>Neevo::OBJECT - Array of rows as objects</li></ul>
   * @return mixed Array or string (if only one value is returned) or FALSE (if nothing is returned).
   */
  public function fetch($type = 1){
    $resource = is_resource($this->resource) ? $this->resource : $this->run();

    $rows=array();
    switch ($type){
      case Neevo::ASSOC;
        while($tmp_rows = @mysql_fetch_assoc($resource))
        $rows[] = $tmp_rows;
        break;
      case Neevo::OBJECT;
        while($tmp_rows = @mysql_fetch_object($resource))
        $rows[] = $tmp_rows;
        break;
      default: $this->error("Fetching result data failed");
    }

    if(count($rows) == 1){ // Only 1 row
      $rows = $rows[0];
      if(count($rows) == 1){ // Only 1 column
        $result = array_values($rows);
        $rows = $result[0];
      }
    }
    if(!count($rows)) $rows = FALSE; // Empty
    return $resource ? $rows : $this->error("Fetching result data failed");
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
    if($this->type!='select') $aff_rows = $this->time() ? @mysql_affected_rows($this->neevo->resource) : false;
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
      'resource' => $this->neevo->resource,
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

    if($this->sql){
      $q = $this->sql;
    }
    else{

      $table = $this->neevo->prefix().$this->table;

      // WHERE statements
      if($this->where){
        $wheres = array();
        $wheres2 = array();
        $wheres3 = array();

        // Set AND glue for queries without glue defined
        foreach ($this->where as $in_where) {
          if($in_where[3]=='') $in_where[3] = 'AND';
          $wheres[] = $in_where;
        }
        unset($in_where);

        // Unset glue for last WHERE clause (defind by former loop)
        unset($wheres[count($wheres)-1][3]);

        // Join WHERE clause array to one string
        foreach ($wheres as $in_where) {
          $in_where[0] = (NeevoStatic::is_sql_function($in_where[0])) ? NeevoStatic::escape_sql_function($in_where[0]) : "`{$in_where[0]}`";
          $in_where[2] = NeevoStatic::escape_string($in_where[2]);
          $wheres2[] = join(' ', $in_where);
        }
        // Remove some spaces
        foreach ($wheres2 as $rplc_where){
          $wheres3[] = str_replace(array(' = ', ' != '), array('=', '!='), $rplc_where);
        }

        $where = " WHERE ".join(' ', $wheres3);
      }

      // INSERT data
      if($this->type == 'insert' && $this->data){
        $icols=array();
        $ivalues=array();
        foreach(NeevoStatic::escape_array($this->data) as $col => $value){
          $icols[]="`$col`";
          $ivalues[]=$value;
        }
        $insert_data = " (".join(', ',$icols).") VALUES (".join(', ',$ivalues).")";
      }

      // UPDATE data
      if($this->type == 'update' && $this->data){
        $update=array();
        foreach(NeevoStatic::escape_array($this->data) as $col => $value){
          $update[]="`$col`=$value";
        }
        $update_data = " SET " . join(', ', $update);
      }

      // ORDER BY statements
      if($this->order){
        $orders = array();
        foreach($this->order as $in_order) {
          $in_order[0] = (NeevoStatic::is_sql_function($in_order[0])) ? $in_order[0] : "`".$in_order[0]."`";
          $orders[] = join(' ', $in_order);
        }
        $order = " ORDER BY ".join(', ', $orders);
      }

      // LIMIT & OFFSET
      if($this->limit) $limit = " LIMIT ".$this->limit;
      if($this->offset) $limit .= " OFFSET ".$this->offset;


      // SELECT query
      if($this->type == 'select'){
        $cols = array();
        foreach ($this->columns as $col) {
          $col = trim($col);
          if($col!='*'){
            if(NeevoStatic::is_as_construction($col)) $col = NeevoStatic::escape_as_construction($col);
            elseif(NeevoStatic::is_sql_function($col)) $col = NeevoStatic::escape_sql_function($col);
            else $col = "`$col`";
          }
          $cols[] = $col;
        }

        $q .= 'SELECT ';
        $q .= join(', ', $cols);
        $q .= ' FROM `'.$table.'`';
        $q .= $where . $order . $limit;
      }

      // INSERT query
      if($this->type == 'insert'){
        $q .= 'INSERT INTO `' . $table . '`';
        $q .= $insert_data;
      }

      // UPDATE query
      if($this->type == 'update'){
        $q .= 'UPDATE `' . $table .'`';
        $q .= $update_data . $where . $order . $limit;
      }

      // DELETE query
      if($this->type == 'delete'){
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
class NeevoStatic {
  
  protected static $highlight_colors = array(
    'background' => '#f9f9f9',
    'columns'    => '#0000ff',
    'chars'      => '#000000',
    'keywords'   => '#008000',
    'joins'      => '#555555',
    'functions'  => '#008000',
    'constants'  => '#ff0000'
    );

  protected static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK', 'RAND');

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
    return is_numeric($string) ? $string : ( is_string($string) ? ( self::is_sql_function($string) ? self::escape_sql_function($string) : "'$string'" ) : $string );
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

  /** Returns formatted filesize */
  public static function filesize($bytes){
    $unit=array('B','kB','MB','GB','TB','PB');
    return @round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$unit[$i];
  }

}

class NeevoException extends Exception{};

?>
