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

  var $q_table;
  var $q_type;
  var $q_where = array();
  

  function  __construct($type = '', $table = ''){
    $this->type($type);
    $this->table($table);
  }

  function table($table){
    $this->q_table = "`$table`";
    return $this;
  }

  function type($type){
    $this->q_type = strtoupper($type);
    return $this;
  }

  function where($where, $value, $glue = null){

    $statement = explode(' ', $where);
    $table = $statement[0];

    if(self::is_sql_function($table)) $table = self::escape_sql_function($table);
    else $table = "`$table`";
    
    $value = self::escape_string($value);

    $condition = array($table, $statement[1], $value, strtoupper($glue));

    $this->q_where[] = $condition;

    return $this;
  }

}

?>