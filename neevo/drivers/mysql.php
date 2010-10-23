<?php
/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://neevo.smasty.net
 * @package  Neevo
 *
 */

/**
 * Neevo MySQL driver (PHP extension 'mysql')
 *
 * Driver connect options:
 * - host => MySQL server name or address
 * - port => MySQL server port
 * - username (or user)
 * - password (or pass, pswd)
 * - database (or db, dbname) => database to select
 * - charset => Character encoding to set (defaults to utf8)
 *
 * @author Martin Srank
 * @package NeevoDrivers
 */
class NeevoDriverMySQL extends NeevoQueryBuilder implements INeevoDriver{

  private $neevo, $resource;


  /**
   * If driver extension is loaded, sets Neevo reference, otherwise throw exception
   * @param Neevo $neevo
   * @throws NeevoException
   * @return void
   */
  public function  __construct(Neevo $neevo){
    if(!extension_loaded("mysql")) throw new NeevoException("PHP extension 'mysql' not loaded.");
    $this->neevo = $neevo;
  }


  /**
   * Creates connection to database
   * @param array $opts Array of connection options
   * @return void
   */
  public function connect(array $opts){

    // Defaults
    if(!isset($opts['charset'])) $opts['charset'] = 'utf8';
    if(!isset($opts['username'])) $opts['username'] = ini_get('mysql.default_user');
    if(!isset($opts['password'])) $opts['password'] = ini_get('mysql.default_password');
    if(!isset($opts['host'])){
      $host = ini_get('mysql.default_host');
      if($host){
        $opts['host'] = $host;
        $opts['port'] = ini_get('mysql.default_port');
      }
      else $opts['host'] = null;
    }

    if(isset($opts['port']))
      $host = $opts['host'] .':'. $opts['port'];
    else $host = $opts['host'];

    // Connect
    $connection = @mysql_connect($host, $opts['username'], $opts['password']);

    if(!is_resource($connection))
      $this->neevo()->error("Connection to host '".$opts['host']."' failed");

    // Select DB
    if($opts['database']){
      $db = mysql_select_db($opts['database']);
      if(!$db) $this->neevo()->error("Could not select database '{$opts['database']}'");
    }

    $this->resource = $connection;

    //Set charset
    if($opts['charset'] && is_resource($connection)){
      if(function_exists('mysql_set_charset'))
				$ok = @mysql_set_charset($opts['charset'], $connection);

      if(!$ok) $this->neevo()->sql("SET NAMES ".$opts['charset'])->run();
    }
  }


  /**
   * Closes connection
   * @return void
   */
  public function close(){
    @mysql_close($this->resource);
  }


  /**
   * Frees memory used by result
   * @param resource $resultSet
   * @return bool
   */
  public function free($resultSet){
    return @mysql_free_result($resultSet);
  }


  /**
   * Executes given SQL query
   * @param string $query_string Query-string.
   * @return resource|bool
   */
  public function query($query_string){
    return @mysql_query($query_string, $this->resource);
  }


  /**
   * Error message with driver-specific additions
   * @param string $neevo_msg Error message
   * @return array Format: array($error_message, $error_number)
   */
  public function error($neevo_msg){
    $mysql_msg = @mysql_error($this->resource);
    $mysql_msg = str_replace('You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use', 'Syntax error', $mysql_msg);

    $msg = $neevo_msg.".";
    if($mysql_msg)
      $msg .= " ".$mysql_msg;

    return array($msg, @mysql_errno($this->resource));
  }


  /**
   * Fetches row from given Query resource as associative array.
   * @param resource $resultSet Query resource
   * @return array
   */
  public function fetch($resultSet){
    return @mysql_fetch_assoc($resultSet);
  }


  /**
   * Move internal result pointer
   * @param resource $resultSet Query resource
   * @param int $row_number Row number of the new result pointer.
   * @return bool
   */
  public function seek($resultSet, $row_number){
    return @mysql_data_seek($resultSet, $row_number);
  }


  /**
   * Get the ID generated in the INSERT query
   * @return int
   */
  public function insertId(){
    return @mysql_insert_id($this->resource);
  }


  /**
   * Randomize result order.
   * @param NeevoQuery $query NeevoQuery instance
   * @return NeevoQuery
   */
  public function rand(NeevoQuery $query){
    $query->order('RAND()');
  }


  /**
   * Number of rows in result set.
   * @param resource $resultSet
   * @return int|FALSE
   */
  public function rows($resultSet){
    return @mysql_num_rows($resultSet);
  }


  /**
   * Number of affected rows in previous operation.
   * @return int
   */
  public function affectedRows(){
    return @mysql_affected_rows($this->resource);
  }


  /**
   * Name of PRIMARY KEY column for table
   * @param string $table
   * @return string|null
   */
  public function getPrimaryKey($table){
    $return = null;
    $arr = array();
    $q = $this->query('SHOW FULL COLUMNS FROM '. $table);
    while($row = $this->fetch($q))
      $arr[] = $row;
    foreach($arr as $col){
      if($col['Key'] === 'PRI' && !isset($return))
        $return = $col['Field'];
    }
    return $return;
  }


  /**
   * Builds Query from NeevoQuery instance
   * @param NeevoQuery $query NeevoQuery instance
   * @return string the Query
   */
  public function build(NeevoQuery $query){

    $where = '';
    $order = '';
    $limit = '';
    $q = '';

    if($query->getSql())
      return $query->getSql().';';

    $table = $query->getTable();

    if($query->getWhere())
      $where = $this->buildWhere($query);

    if($query->getOrder())
      $order = $this->buildOrder($query);

    if($query->getLimit()) $limit = " LIMIT " .$query->getLimit();
    if($query->getOffset()) $limit .= " OFFSET " .$query->getOffset();

    if($query->getType() == 'select'){
      $cols = $this->buildSelectCols($query);
      $q .= "SELECT $cols FROM $table$where$order$limit";
    }

    elseif($query->getType() == 'insert' && $query->getData()){
      $insert_data = $this->buildInsertData($query);
      $q .= "INSERT INTO $table$insert_data";
    }

    elseif($query->getType() == 'update' && $query->getData()){
      $update_data = $this->buildUpdateData($query);
      $q .= "UPDATE $table$update_data$where$order$limit";
    }

    elseif($query->getType() == 'delete')
      $q .= "DELETE FROM $table$where$order$limit";

    return $q.';';
  }


  /**
   * Escapes given value
   * @param mixed $value
   * @param int $type Type of value (Neevo::TEXT, Neevo::BOOL...)
   * @return mixed
   */
  public function escape($value, $type){
    switch($type){
      case Neevo::BOOL:
        return $value ? 1 :0;

      case Neevo::TEXT:
        return "'". mysql_real_escape_string($value, $this->resource) ."'";
        break;

      case Neevo::BINARY:
        return "_binary'". mysql_real_escape_string($value, $this->resource) ."'";

      case Neevo::DATETIME:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d H:i:s'") : date("'Y-m-d H:i:s'", $value);

      case Neevo::DATE:
        return ($value instanceof DateTime) ? $value->format("'Y-m-d'") : date("'Y-m-d'", $value);
        
      default:
        $this->neevo()->error('Unsupported data type');
        break;
    }
  }


  /**
   * Return Neevo class instance
   * @return Neevo
   */
  public function neevo(){
    return $this->neevo;
  }

}