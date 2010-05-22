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
 * @link       http://labs.smasty.net
 * @package    neevo
 * @version    0.03dev
 */


/** Main Neevo layer class
 * @package neevo
 */
class Neevo{

  var $resource_ID;
  var $queries = 0;
  var $last = '';
  var $error_reporting = 1;
  var $table_prefix = '';

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

  function options(){
    return get_object_vars($this);
  }

  /**
   * Cnnect to database
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
   * @return boolean
   */
  public final function query($query, $count = true){
    $q = @mysql_query($query, $this->resource_ID);
    $count ? $this->queries++ : false;
    $this->last=$query;
    return $q ? $q : $this->error("Query failed");
  }

  /** Highlights given MySQL query */
  protected static function highlight_sql($sql){
    $colors=array('chars'=>'black','keywords'=>'green','joins'=>'grey','functions'=>'green','constants'=>'red');
    $words=array('keywords'=>array('SELECT', 'UPDATE', 'INSERT', 'DELETE', 'REPLACE', 'INTO', 'CREATE', 'ALTER', 'TABLE', 'DROP', 'TRUNCATE', 'FROM', 'ADD', 'CHANGE', 'COLUMN', 'KEY', 'WHERE', 'ON', 'CASE', 'WHEN', 'THEN', 'END', 'ELSE', 'AS', 'USING', 'USE', 'INDEX', 'CONSTRAINT', 'REFERENCES', 'DUPLICATE', 'LIMIT', 'OFFSET', 'SET', 'SHOW', 'STATUS', 'BETWEEN', 'AND', 'IS', 'NOT', 'OR', 'XOR', 'INTERVAL', 'TOP', 'GROUP BY', 'ORDER BY', 'DESC', 'ASC', 'COLLATE', 'NAMES', 'UTF8', 'DISTINCT', 'DATABASE', 'CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'MATCH', 'AGAINST', 'LIKE', 'REGEXP', 'RLIKE', 'PRIMARY', 'AUTO_INCREMENT', 'DEFAULT', 'IDENTITY', 'VALUES', 'PROCEDURE', 'FUNCTION', 'TRAN', 'TRANSACTION', 'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'TRIGGER', 'CASCADE', 'DECLARE', 'CURSOR', 'FOR', 'DEALLOCATE'),
      'joins' => array('JOIN', 'INNER', 'OUTER', 'FULL', 'NATURAL', 'LEFT', 'RIGHT'),
      'functions' => self::$sql_functions,
      'chars' => '/([\\.,\\(\\)<>:=`]+)/i',
      'constants' => '/(\'[^\']*\'|[0-9]+)/i');
    $sql=str_replace('\\\'','\\&#039;',$sql);
    foreach($colors as $key=>$color){
      $regexp=in_array($key,array('constants','chars')) ? $words[$key] : '/\\b('.join("|",$words[$key]).')\\b/i';
      $sql=preg_replace($regexp,'<span style="color:'.$color."\">$1</span>",$sql);
    }
    return "<code style=\"color:00f;background:#f9f9f9\"> $sql </code>";
  }

  /** Replaces placeholders in string with value/s from array/string
   *
   * @param string String with placeholders '%1, etc.'
   * @param mixed Array/string with values to replace
   * @return string Replaced string
   */
  protected static function printf($string, $values){
    preg_match_all("/\%(\d*)/", $string, $replace);
    return str_replace($replace[0], is_array($values) ? self::escape_array($values) : self::escape_string($values), $string);
  }

  /** Escapes whole array for use in MySQL */
  protected static function escape_array(array $array){
    $result=array();
    if(get_magic_quotes_gpc()==0){
      foreach($array as $key => $value){
         $result[$key] = is_numeric($value) ? $value : ( is_string($value) ? self::escape_string($value) : ( is_array($value) ? self::escape_array($value) : $value ) );
      }
      return $result;
    }
    else return $array;
  }

  /** Escapes given string for use in MySQL */
  protected static function escape_string($string){
    $string=str_replace('\'', '\\\'' ,$string);
    return is_string($string) ? ( self::is_sql_function($string) ? escape_sql_function($string) : "'$string'" ) : $string;
  }

  /** Checks whether a given string is a SQL function or not */
  protected static function is_sql_function($string){
    if(is_string($string)){
      $is_plmn = preg_match("/^(\w*)(\+|-)(\w*)/", $string);
      $var = is_string($string) ? strtoupper(preg_replace('/[^a-zA-Z0-9_\(\)]/','',$string)) : false;
      $is_sql = in_array(preg_replace('/\(.*\)/','',$var), self::$sql_functions);
      return ($is_sql || $is_plmn) ? true : false;
    }
    else return false;
  }

  /** Escapes given SQL function  */
  protected static function escape_sql_function($sql_function){
    return str_replace(array('("','")'), array('(\'','\')'), $sql_function);
  }

  /** Checks whether a given string is a MySQL 'AS construction' ([SELECT] cars AS vegetables) */
  protected static function is_as_construction($string){
    return is_string($string) ? ( preg_match('/(.*) as \w*/i', $string) ? true : false ) :  false;
  }

  /** Escapes (quotes column name with ``) given 'AS construction'  */
  protected static function escape_as_construction($as_construction){
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
  protected function error($err_neevo){
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

class NeevoMySQLQuery extends Neevo {

  private $q_table, $q_type, $q_limit, $q_offset;
  private $q_where, $q_order, $q_columns = array();


  function  __construct($options, $type = '', $table = ''){
    $this->type($type);
    $this->table($table);

    foreach ($options as $key => $value) {
      $this->$key = $value;
    }
  }

  function table($table){
    $this->q_table = $table;
    return $this;
  }

  function type($type){
    $this->q_type = $type;
    return $this;
  }

  function columns($columns){
    if(!is_array($columns)) $columns = explode(',', $columns);
    $cols = array();
    foreach ($columns as $col) {
      $col = trim($col);
      if($col!='*'){
        if($this->is_as_construction($col)) $col = $this->escape_as_construction($col);
        elseif($this->is_sql_function($col)) $col = $this->escape_sql_function($col);
        else $col = "`$col`";
      }
      $cols[] = $col;
    }
    $this->q_columns = $cols;
    return $this;
  }

  function where($where, $value, $glue = null){

    $where_condition = explode(' ', $where);
    $column = $where_condition[0];

    if(self::is_sql_function($column)) $column = self::escape_sql_function($column);
    else $column = "`$column`";

    $value = self::escape_string($value);

    $condition = array($column, $where_condition[1], $value, strtoupper($glue));

    $this->q_where[] = $condition;

    return $this;
  }

  function order($args){
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

  function limit($limit, $offset = null){
    $this->q_limit = $limit;
    if(isset($offset)) $this->q_offset = $offset;
    return $this;
  }

  function dump($color = true){
    echo $color ? self::highlight_sql($this->build()) : $this->build();
  }

  function run(){
    return $this->query($this->build());
  }

  function build(){

    // @todo: ak nezadal glue, pouzit AND.
    $wheres = array();
    foreach ($this->q_where as $in_where) {
      $wheres[] = implode(' ', $in_where);
    }
    $where = " WHERE ".implode(' ', $wheres);

    $orders = array();
    foreach($this->q_order as $in_order) {
      $orders[] = implode(' ', $in_order);
    }
    $order = " ORDER BY ".implode(', ', $orders);

    if($this->q_limit) $limit = " LIMIT ".$this->q_limit;
    if($this->q_offset) $limit .= " OFFSET ".$this->q_offset;

    if($this->q_type=='select'){
      $q .= 'SELECT ';
      $q .= implode(', ', $this->q_columns);
      $q .= ' FROM `'.$this->q_table.'`';
      $q .= $where . $order . $limit;
    }

    return $q;

  }

}

?>