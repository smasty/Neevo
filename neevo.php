<?php
/**
 * Neevo - Tiny open-source MySQL layer
 *
 * Copyright (c) 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the GNU LGPL license that is bundled
 * with this package in the file license.txt.
 *
 * @copyright  Copyright (c) 2010 Martin Srank (http://smasty.net)
 * @license    http://www.gnu.org/copyleft/lesser.html  GNU LGPL
 * @link       http://labs.smasty.net/neevo/
 * @package    neevo
 * @version    0.01dev
 */


/** Main Neevo layer class
 * @package neevo
 */
class Neevo{

/* ==========  VARIABLES  ========== */

  var $resource_ID;
  var $queries = 0;
  var $last = '';
  var $error_reporting = 1;
  var $table_prefix = '';

  static $highlight_colors = array(
    'columns' => '#0000ff',
    'chars' => '#000000',
    'keywords' => '#008000',
    'joins' => '#555555',
    'functions' => '#008000',
    'constants' => '#ff0000'
    );

/* ==========  CONSTANTS  ========== */

  const ASSOC=1;
  const NUM=2;
  const OBJECT=3;

  protected static $sql_functions=array('MIN', 'MAX', 'SUM', 'COUNT', 'AVG', 'CAST', 'COALESCE', 'CHAR_LENGTH', 'LENGTH', 'SUBSTRING', 'DAY', 'MONTH', 'YEAR', 'DATE_FORMAT', 'CRC32', 'CURDATE', 'SYSDATE', 'NOW', 'GETDATE', 'FROM_UNIXTIME', 'FROM_DAYS', 'TO_DAYS', 'HOUR', 'IFNULL', 'ISNULL', 'NVL', 'NVL2', 'INET_ATON', 'INET_NTOA', 'INSTR', 'FOUND_ROWS', 'LAST_INSERT_ID', 'LCASE', 'LOWER', 'UCASE', 'UPPER', 'LPAD', 'RPAD', 'RTRIM', 'LTRIM', 'MD5', 'MINUTE', 'ROUND', 'SECOND', 'SHA1', 'STDDEV', 'STR_TO_DATE', 'WEEK');


  /**
   * Constructor
   * @param array $opts
   * <pre>Array(
   *   host      =>  mysql_host,
   *   username  =>  mysql_username,
   *   password  =>  mysql_password,
   *   database  =>  mysql_database,
   *   encoding  =>  mysql_encoding
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
   * @param array $opts
   * @return boolean
   */
  protected function connect(array $opts){
    $connection = @mysql_connect($opts['host'], $opts['username'], $opts['password']);
    $this->resource_ID = $connection;
    return (bool) $connection or self::error("Connection to host '".$opts['host']."' failed");
  }


  /**
   * Sets table names/encoding
   * @param string $encoding
   * @return boolean
   */
  protected function set_encoding($encoding){
    if($encoding){
      $query = $this->query("SET NAMES $encoding", false);
      return (bool) $query;
    } else return true;
  }


  /**
   * Selects database to use
   * @param string $db_name
   * @return boolean
   */
  protected function select_db($db_name){
    $select = @mysql_select_db($db_name, $this->resource_ID);
    return (bool) $select or $this->error("Failed selecting database '$db_name'");
  }


  /**
   * Sets and/or returns table prefix
   * @param string $prefix
   * @return mixed
   */
  public function prefix($prefix = null){
    if(isset($prefix)) $this->table_prefix = $prefix;
    return $this->table_prefix;
  }


  /**
   * Sets and/or returns error reporting
   * @param boolan $value
   * @return boolean
   */
  public function errors($value = null){
    if(isset($value))$this->error_reporting = $value;
    return $this->error_reporting;
  }


  /**
   * Performs MySQL query
   * @param string $query Query to perform
   * @param boolean $count Count this query or not?
   * @return resource
   */
  public final function query($query, $count = true){
    $q = @mysql_query($query, $this->resource_ID);
    $count ? $this->queries++ : false;
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
  public final function insert($table,array $data){
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
  public final function update($table,array $data){
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

/* ==========  PUBLIC RESULT-FETCHING METHODS  ========== */

  /** 
   * Fetches data from given MySQL query
   * @param string $query MySQL (SELECT) query
   * @param int $type Result data as: Possible values:<pre>
   *    - Neevo::ASSOC  (1) - fetches an array with associative arrays as table rows
   *    - Neevo::NUM    (2) - fetches an array with numeric arrays as table rows
   *    - Neevo::OBJECT (3) - fetches an array with objects as table rows</pre>
   * @return array Associative array built from query
   */
  public static function fetch($query, $type=1){
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
    if(count($arr)==1) $arr = $arr[0];
    return $query ? $arr : self::error("Fetching result data failed");
    @mysql_free_result($query);
  }

  /** Returns data from given MySQL query
   *
   * {@source}
   * @param string $query MySQL (SELECT) query
   * @param int $jump Jump to result row number (starts from 0)
   * @return string Result data
   */
  public static function result($query, $jump=0){
    return $query ? @mysql_result($query, $jump) : self::error("Return data failed");
    @mysql_free_result($query);
  }

  public function rows($resource_ID){
    return @mysql_num_rows($resource_ID);
  }

  /** Highlights given MySQL query */
  public static function highlight_sql($sql){
    $chcolors = array('chars'=>'black','keywords'=>'green','joins'=>'grey','functions'=>'green','constants'=>'red');
    $hcolors = self::$highlight_colors;
    unset($hcolors['columns']);

    $words = array('keywords'=>array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins' => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars' => '/([\\.,\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i');

    $sql=str_replace('\\\'','\\&#039;',$sql);

    foreach($chcolors as $key=>$color){
      $regexp=in_array($key,array('constants','chars')) ? $words[$key] : '/\\b('.join("|",$words[$key]).')\\b/i';
      $sql=preg_replace($regexp, "<span style=\"color:$color\">$1</span>",$sql);
    }

    $sql = str_replace($chcolors, $hcolors, $sql);
    return "<code style=\"color:".self::$highlight_colors['columns'].";background:#f9f9f9\"> $sql </code>\n";
  }

  /** Replaces placeholders in string with value/s from array/string
   *
   * @param string String with placeholders '%1, etc.'
   * @param mixed Array/string with values to replace
   * @return string Replaced string
   */
  public static function printf($string, $values){
    preg_match_all("/\%(\d*)/", $string, $replace);
    return str_replace($replace[0], is_array($values) ? self::escape_array($values) : self::escape_string($values), $string);
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

  /** Escapes (quotes column name with ``) given 'AS construction'  */
  public static function escape_as_construction($as_construction){
    $construction=explode(' ', $as_construction);
    $escape = preg_match('/^\w{1,}$/', $construction[0]) ? true : false;
    if($escape){
      $construction[0]="`".$construction[0]."`";
    }
    $as_construction=join(' ', $construction);
    return preg_replace('/(.*) (as) (\w*)/i','$1 AS `$3`',$as_construction);
  }

  /**
   * Generates E_USER_WARNING
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

}





class NeevoMySQLQuery {

  private $q_table, $q_type, $q_limit, $q_offset, $neevo;
  private $q_where, $q_order, $q_columns, $q_data = array();

  /**
   * Constructor
   * @param array $options
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
   * Sets query type. Possibe values: select, insert, update, delete)
   * @param string $type
   * @return NeevoMySQLQuery
   */
  public function type($type){
    $this->q_type = $type;
    return $this;
  }

  /**
   * Sets columns to retrive in SELECT queries
   * @param mixed $columns
   * @return NeevoMySQLQuery
   */
  public function cols($columns){
    if(!is_array($columns)) $columns = explode(',', $columns);
    $cols = array();
    foreach ($columns as $col) {
      $col = trim($col);
      if($col!='*'){
        if(Neevo::is_as_construction($col)) $col = Neevo::escape_as_construction($col);
        elseif(Neevo::is_sql_function($col)) $col = Neevo::escape_sql_function($col);
        else $col = "`$col`";
      }
      $cols[] = $col;
    }
    $this->q_columns = $cols;
    return $this;
  }

  /**
   * Data for INSERT and UPDATE queries
   * @param array $data data in format "$column=>$value"
   * @return NeevoMySQLQuery
   */
  public function data(array $data){
    $this->q_data = $data;
    return $this;
  }

  /**
   * Sets WHERE condition for Query
   * @param string $where Column to use and optionaly operator: "email LIKE" or "email !="
   * @param string $value Value to search for: "%&#64;example.%" or "spam&#64;example.com"
   * @param string $glue Operator (AND, OR, etc.) to use betweet this and next WHERE condition
   * @return NeevoMySQLQuery
   */
  public function where($where, $value, $glue = null){

    $where_condition = explode(' ', $where);
    if(!isset($where_condition[1])) $where_condition[1] = '=';
    $column = $where_condition[0];

    if(Neevo::is_sql_function($column)) $column = Neevo::escape_sql_function($column);
    else $column = "`$column`";

    $value = Neevo::escape_string($value);

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
    if(isset($offset)) $this->q_offset = $offset;
    return $this;
  }
  
  /**
   * Prints consequential Query (highlighted by default)
   * @param bool $color Highlight query or not
   */
  public function dump($color = true){
    echo $color ? Neevo::highlight_sql($this->build()) : $this->build();
    return $this;
  }

  /**
   * Performs Query
   * @return resource
   */
  public function run(){
    return $this->neevo->query($this->build());
  }

  /**
   * Builds Query from NeevoMySQLQuery instance
   * @return string Query
   */
  public function build(){

    $table = $this->neevo->prefix().$this->q_table;

    // WHERE statements
    if($this->q_where){
      $wheres = array();
      $wheres2 = array();
      foreach ($this->q_where as $in_where) {
        if($in_where[3]=='') $in_where[3] = 'AND';
        $wheres[] = $in_where;
      }


      unset($wheres[count($wheres)-1][3]);

      foreach ($wheres as $in_where2) {
        $wheres2[] = join(' ', $in_where2);
      }

      $where = " WHERE ".join(' ', $wheres2);
    }

    // INSERT data
    if($this->q_type == 'insert' && $this->q_data){
      $icols=array();
      $ivalues=array();
      foreach(Neevo::escape_array($this->q_data) as $col => $value){
        $icols[]="`$col`";
        $ivalues[]=$value;
      }
      $insert_data = " (".join(', ',$icols).") VALUES (".join(', ',$ivalues).")";
    }

    // UPDATE data
    if($this->q_type == 'update' && $this->q_data){
      $update=array();
      foreach(Neevo::escape_array($this->q_data) as $col => $value){
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

    return $q;

  }

}

?>